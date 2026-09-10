<?php

namespace App\Services\Routing\Safety;

use App\Contracts\AccidentDataProvider;
use App\Safety\SafetySignal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proveedor de siniestralidad vial desde datasets públicos (Socrata).
 *
 * Dataset por defecto: "SECTORES CRITICOS DE SINIESTRALIDAD VIAL" (ANSV),
 * datos.gov.co/d/rs3u-8r4q — licencia CC BY-SA 4.0, cobertura nacional sobre
 * la red vial de carreteras (primaria y secundaria), sectores críticos con
 * fallecidos (2015-2019), acceso público (grants: public viewer), sin API key
 * para consultas de bajo volumen; se pueden añadir tokens gratuitos.
 *
 * Deshabilitado por defecto (SAFETY_ACCIDENTS_ENABLED=false).
 *
 * Regla de honestidad: solo produce una señal cuando el dataset registra
 * sectores críticos reales cerca de la ruta (count > 0). Si no hay
 * coincidencias, el factor se excluye: NO se concluye seguridad por la
 * ausencia de registros (el dataset nacional no documenta calles urbanas).
 */
class SocrataAccidentDataProvider implements AccidentDataProvider
{
    protected bool $enabled;

    protected string $baseUrl;

    protected string $dataset;

    protected string $appToken;

    protected string $latColumn;

    protected string $lngColumn;

    protected int $radiusM;

    protected int $maxRows;

    protected int $timeout;

    protected int $cacheTtl;

    protected float $penaltyPerDensity;

    public function __construct()
    {
        $cfg = config('safety.accidents.socrata', []);
        $this->enabled = (bool) config('safety.accidents.enabled', false);
        $this->baseUrl = rtrim((string) ($cfg['base_url'] ?? 'https://www.datos.gov.co/resource'), '/');
        $this->dataset = (string) ($cfg['dataset_id'] ?? 'rs3u-8r4q');
        $this->appToken = (string) ($cfg['app_token'] ?? '');
        $this->latColumn = (string) ($cfg['lat_column'] ?? 'latitud');
        $this->lngColumn = (string) ($cfg['lng_column'] ?? 'longitud');
        $this->radiusM = (int) ($cfg['radius_m'] ?? 200);
        $this->maxRows = (int) ($cfg['max_rows'] ?? 2000);
        $this->timeout = (int) ($cfg['timeout'] ?? 20);
        $this->cacheTtl = (int) ($cfg['cache_ttl'] ?? 86400);
        $this->penaltyPerDensity = (float) ($cfg['penalty_per_density'] ?? 25);
    }

    public function name(): string
    {
        return (string) (config('safety.accidents.socrata.source.name')
            ?? 'Sectores críticos de siniestralidad vial (datos abiertos)');
    }

    public function sourceDescription(): array
    {
        $source = config('safety.accidents.socrata.source', []);

        return [
            'name' => $source['name'] ?? $this->name(),
            'url' => $source['url'] ?? 'https://www.datos.gov.co/d/'.$this->dataset,
            'license' => $source['license'] ?? 'CC BY-SA 4.0',
            'coverage' => $source['coverage'] ?? 'Nacional: red vial de carreteras (sectores críticos con fallecidos).',
            'update_frequency' => 'Anual',
            'api_key_required' => $this->appToken !== '',
        ];
    }

    /**
     * @param  array<int, array{lat: float, lng: float}>  $coordinates
     * @return array<int, SafetySignal>
     */
    public function signals(array $coordinates): array
    {
        if (! $this->enabled || count($coordinates) < 2) {
            return [];
        }

        $coordinates = $this->normalizeCoordinates($coordinates);

        $routeM = $this->routeLength($coordinates);
        if ($routeM < 1.0) {
            return [];
        }

        $records = $this->recordsNear($coordinates);

        if (empty($records)) {
            // Sin registros reales cerca: el factor NO aporta y el score de
            // la ruta no podrá usar la siniestralidad (cobertura excluida).
            // No se concluye seguridad (ver docblock del método).
            Log::debug('[SAFETY] Siniestralidad: sin sectores críticos registrados en el corredor', [
                'dataset' => $this->dataset,
                'bbox_m' => $this->radiusM,
            ]);

            return [];
        }

        $density = count($records) / ($routeM / 1000.0);
        $score = max(0.0, 100.0 - $density * $this->penaltyPerDensity);

        $fatalities = 0;
        $locations = [];
        foreach ($records as $record) {
            $fatalities += (int) ($record['fallecidos'] ?? 0);
            $locationName = (string) ($record['numeración'] ?? $record['nombre'] ?? $record['tramo'] ?? '');
            if (count($locations) < 3 && $locationName !== '') {
                $locations[] = $locationName;
            }
        }

        return [
            new SafetySignal(
                type: 'accidents',
                value: $score,
                coverage: 1.0,
                source: $this->name(),
                confidence: 1.0,
                metadata: [
                    'count' => count($records),
                    'per_km' => round($density, 2),
                    'fatalities_total' => $fatalities,
                    'locations' => array_values(array_filter($locations)),
                    'dataset' => $this->dataset,
                    'source_url' => $this->sourceDescription()['url'],
                ],
            ),
        ];
    }

    /**
     * Consulta (y cachea por bbox) los registros del dataset que intersectan
     * el corredor ampliado alrededor de la ruta.
     *
     * @param  array<int, array{lat: float, lng: float}>  $coordinates
     * @return array<int, array<string, mixed>>
     */
    protected function recordsNear(array $coordinates): array
    {
        $bounds = $this->bounds($coordinates);
        $cacheKey = 'safety:accidents:'.$this->dataset.':'.sprintf(
            '%.3f|%.3f|%.3f|%.3f',
            $bounds['minLat'],
            $bounds['minLng'],
            $bounds['maxLat'],
            $bounds['maxLng']
        );

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($bounds) {
            return $this->fetchSoda($bounds);
        });
    }

    /**
     * @param  array{minLat: float, minLng: float, maxLat: float, maxLng: float}  $bounds
     * @return array<int, array<string, mixed>>
     */
    protected function fetchSoda(array $bounds): array
    {
        $url = $this->baseUrl.'/'.$this->dataset.'.json';

        $query = "{$this->latColumn} between {$bounds['minLat']} and {$bounds['maxLat']} AND {$this->lngColumn} between {$bounds['minLng']} and {$bounds['maxLng']}";

        $params = [
            '$where' => $query,
            '$limit' => $this->maxRows,
            '$order' => $this->latColumn.' ASC',
        ];

        if ($this->appToken !== '') {
            $params['$$app_token'] = $this->appToken;
        }

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])->timeout($this->timeout)->get($url, $params);
        } catch (\Throwable $e) {
            Log::warning('[SAFETY] Dataset de siniestralidad no disponible', [
                'dataset' => $this->dataset,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        if (! $response->successful()) {
            Log::warning('[SAFETY] Dataset de siniestralidad respondió estado '.$response->status(), [
                'url' => $url,
            ]);

            return [];
        }

        $rows = $response->json() ?? [];

        return is_array($rows) ? $rows : [];
    }

    /**
     * Normaliza coordenadas del motor de rutas ([[lat, lng], ...]) o del
     * formato interno (['lat'=>, 'lng'=>]) a un único formato asociativo.
     *
     * @param  array<int, array{lat: float, lng: float}|array{0: float, 1: float}>  $coordinates
     * @return array<int, array{lat: float, lng: float}>
     */
    protected function normalizeCoordinates(array $coordinates): array
    {
        $normalized = [];
        foreach ($coordinates as $point) {
            if (! is_array($point)) {
                continue;
            }
            if (array_key_exists('lat', $point) && array_key_exists('lng', $point)) {
                $normalized[] = ['lat' => (float) $point['lat'], 'lng' => (float) $point['lng']];
            } elseif (isset($point[0], $point[1])) {
                $normalized[] = ['lat' => (float) $point[0], 'lng' => (float) $point[1]];
            }
        }

        return $normalized;
    }

    /**
     * @param  array<int, array{lat: float, lng: float}>  $coordinates
     * @return array{minLat: float, minLng: float, maxLat: float, maxLng: float}
     */
    protected function bounds(array $coordinates): array
    {
        $minLat = 90.0;
        $minLng = 180.0;
        $maxLat = -90.0;
        $maxLng = -180.0;

        foreach ($coordinates as $point) {
            $minLat = min($minLat, $point['lat']);
            $minLng = min($minLng, $point['lng']);
            $maxLat = max($maxLat, $point['lat']);
            $maxLng = max($maxLng, $point['lng']);
        }

        $margin = $this->radiusM / 111320.0;

        return [
            'minLat' => $minLat - $margin,
            'minLng' => $minLng - $margin,
            'maxLat' => $maxLat + $margin,
            'maxLng' => $maxLng + $margin,
        ];
    }

    /**
     * @param  array<int, array{lat: float, lng: float}>  $coordinates
     */
    protected function routeLength(array $coordinates): float
    {
        $total = 0.0;
        for ($i = 0; $i < count($coordinates) - 1; $i++) {
            $total += $this->distanceMeters($coordinates[$i], $coordinates[$i + 1]);
        }

        return $total;
    }

    /**
     * @param  array{lat: float, lng: float}  $a
     * @param  array{lat: float, lng: float}  $b
     */
    protected function distanceMeters(array $a, array $b): float
    {
        $earth = 6371000.0;
        $dLat = deg2rad($b['lat'] - $a['lat']);
        $dLng = deg2rad($b['lng'] - $a['lng']);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($a['lat'])) * cos(deg2rad($b['lat'])) * sin($dLng / 2) ** 2;

        return 2 * $earth * asin(min(1.0, sqrt($h)));
    }
}