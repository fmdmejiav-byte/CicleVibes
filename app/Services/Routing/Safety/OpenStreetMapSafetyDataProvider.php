<?php

namespace App\Services\Routing\Safety;

use App\Contracts\SafetyDataProvider;
use App\Safety\SafetySignal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proveedor de seguridad basado en OpenStreetMap (Overpass API).
 *
 * Analiza la geometría de la ruta muestreando puntos cada N metros y busca
 * en OpenStreetMap la vía más cercana (corredor) para leer sus etiquetas
 * REALES: highway, maxspeed, surface, lit, cycleway, bicycle, etc.
 *
 * Reglas de honestidad:
 *  - Solo se usan etiquetas documentadas; la ausencia de una etiqueta
 *    cuenta como "sin dato" (reduce cobertura, nunca inventa).
 *  - La consulta Overpass se acota al corredor de la ruta y se cachea por
 *    corredor (TTL configurable) para respetar la política de uso pública.
 *  - Si la API falla o devuelve vacío, el proveedor devuelve un set vacío y
 *    registra la incidencia en el log; nunca inventa señales.
 */
class OpenStreetMapSafetyDataProvider implements SafetyDataProvider
{
    protected string $baseUrl;

    protected int $timeout;

    protected int $radius;

    protected int $step;

    protected int $maxSamples;

    protected int $cacheTtl;

    public function __construct()
    {
        $osm = config('safety.osm', []);
        $this->baseUrl = rtrim((string) ($osm['overpass_url'] ?? config('services.map.overpass_url')), '/');
        $this->timeout = (int) ($osm['timeout'] ?? 25);
        $this->radius = (int) ($osm['corridor_radius_m'] ?? 60);
        $this->step = (int) ($osm['sample_step_m'] ?? 60);
        $this->maxSamples = (int) ($osm['max_samples'] ?? 250);
        $this->cacheTtl = (int) ($osm['cache_ttl'] ?? 86400);
    }

    public function name(): string
    {
        return 'OpenStreetMap (Overpass API)';
    }

    public function sourceDescription(): array
    {
        return [
            'name' => 'OpenStreetMap',
            'url' => 'https://www.openstreetmap.org/',
            'api' => 'Overpass API - '.$this->baseUrl,
            'license' => 'ODbL 1.0 (Open Database License)',
            'coverage' => 'Mundial, con buena densidad en Colombia; cobertura real solo donde la comunidad ha mapeado etiquetas (highway, maxspeed, surface, lit, cycleway). La ausencia de datos NO implica ausencia de infraestructura.',
            'update_frequency' => 'Continua (edición comunitaria, sin garantía)',
            'rate_limits' => 'Servidor público overpass-api.de: ~1-2 consultas/seg, max 4 hilos; consultas específicas via POST, respetando caché por corredor.',
            'api_key_required' => false,
        ];
    }

    /**
     * @param  array<int, array{lat: float, lng: float}>  $coordinates
     * @return array<int, SafetySignal>
     */
    public function signals(array $coordinates): array
    {
        if (! (bool) config('safety.osm.enabled', true)) {
            return [];
        }

        $coordinates = $this->normalizeCoordinates($coordinates);

        $points = $this->samplePoints($coordinates);

        if (count($points) < 2 || $this->routeLength($coordinates) < 1.0) {
            return [];
        }

        $elements = $this->corridorElements($coordinates);

        if (empty($elements['ways']) && empty($elements['nodes'])) {
            // Overpass respondió correctamente pero sin datos mapeados en el
            // corredor: cobertura real nula. Registramos la ausencia.
            Log::debug('[SAFETY] Overpass sin datos en el corredor analizado', [
                'points' => count($points),
                'route_m' => round($this->routeLength($coordinates), 1),
            ]);
        }

        return $this->buildSignals($coordinates, $points, $elements);
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
     * Remuestrea la geometría a puntos espaciados cada N metros (con tope).
     *
     * @param  array<int, array{lat: float, lng: float}>  $coordinates
     * @return array<int, array{lat: float, lng: float}>
     */
    protected function samplePoints(array $coordinates): array
    {
        if (count($coordinates) < 2) {
            return $coordinates;
        }

        $points = [];
        $accumulated = 0.0;
        $positions = [];
        $lengths = [];
        $totalMeters = 0.0;

        for ($i = 0; $i < count($coordinates) - 1; $i++) {
            $a = $coordinates[$i];
            $b = $coordinates[$i + 1];
            $segmentM = $this->distanceMeters($a, $b);
            $lengths[$i] = $segmentM;
            $totalMeters += $segmentM;
            $positions[$i] = $a;
        }
        $positions[count($coordinates) - 1] = $coordinates[count($coordinates) - 1];
        $lengths[count($coordinates) - 1] = 0.0;

        if ($totalMeters <= 0.0) {
            return [$coordinates[0]];
        }

        // Número de muestras según el paso real y el tope configurado.
        $count = (int) floor($totalMeters / max(1, $this->step));
        $count = min($count, $this->maxSamples);

        for ($k = 0; $k <= $count; $k++) {
            $target = ($k / max(1, $count)) * $totalMeters;
            $points[] = $this->pointAtDistance($positions, $lengths, $target);
        }

        // Asegurar extremos exactos.
        $points[0] = $coordinates[0];
        $points[count($points) - 1] = $coordinates[count($coordinates) - 1];

        return $points;
    }

    /**
     * Devuelve la coordenada a `distance` metros a lo largo de la ruta.
     *
     * @param  array<int, array{lat: float, lng: float}>  $positions
     * @param  array<int, float>  $lengths
     * @return array{lat: float, lng: float}
     */
    protected function pointAtDistance(array $positions, array $lengths, float $distance): array
    {
        $walked = 0.0;
        foreach ($lengths as $i => $segmentM) {
            if ($walked + $segmentM >= $distance) {
                if ($segmentM <= 0.0) {
                    return $positions[$i];
                }
                $ratio = ($distance - $walked) / $segmentM;
                $a = $positions[$i];
                $b = $positions[$i + 1];

                return [
                    'lat' => $a['lat'] + ($b['lat'] - $a['lat']) * $ratio,
                    'lng' => $a['lng'] + ($b['lng'] - $a['lng']) * $ratio,
                ];
            }
            $walked += $segmentM;
        }
        $last = $positions[count($positions) - 1];

        return $last;
    }

    /**
     * Consulta (y cachea) los elementos Overpass del corredor que rodea la
     * ruta. La clave de caché redondea el bounding box a ~1 km para
     * aprovechar rutas cercanas sin repetir consultas.
     *
     * @param  array<int, array{lat: float, lng: float}>  $coordinates
     * @return array{ways: array<int, array<string, mixed>>, nodes: array<int, array<string, mixed>>}
     */
    protected function corridorElements(array $coordinates): array
    {
        $bounds = $this->bounds($coordinates);
        $cacheKey = 'safety:osm:corridor:'.sprintf(
            '%.2f|%.2f|%.2f|%.2f|%d|%d|%d',
            $bounds['minLat'],
            $bounds['minLng'],
            $bounds['maxLat'],
            $bounds['maxLng'],
            $this->step,
            $this->radius,
            $this->maxSamples
        );

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($coordinates) {
            return $this->fetchElements($coordinates);
        });
    }

    /**
     * @param  array<int, array{lat: float, lng: float}>  $coordinates
     * @return array{ways: array<int, array<string, mixed>>, nodes: array<int, array<string, mixed>>}
     */
    protected function fetchElements(array $coordinates): array
    {
        $points = $this->samplePoints($coordinates);
        $coords = [];
        foreach ($points as $point) {
            $coords[] = sprintf('%.7f,%.7f', $point['lat'], $point['lng']);
        }

        $wayRadius = (int) min(120, $this->radius + 40);
        $nodeRadius = 45;

        $query = '[out:json][timeout:'.$this->timeout.'];('.
            'way["highway"](around:'.$wayRadius.','.implode(',', $coords).');'.
            'node["highway"="crossing"](around:'.$nodeRadius.','.implode(',', $coords).');'.
            'node["traffic_calming"](around:'.$nodeRadius.','.implode(',', $coords).');'.
            ');out tags geom;';

        try {
            $response = Http::withHeaders([
                'User-Agent' => config('services.map.user_agent', 'CicleVibes/1.0'),
                'Accept' => 'application/json',
            ])->asForm()->timeout($this->timeout + 15)->post($this->baseUrl, ['data' => $query]);
        } catch (\Throwable $e) {
            Log::warning('[SAFETY] Overpass API no disponible para análisis de seguridad', [
                'error' => $e->getMessage(),
            ]);

            return ['ways' => [], 'nodes' => []];
        }

        if (! $response->successful()) {
            Log::warning('[SAFETY] Overpass respondió con estado '.$response->status(), [
                'body' => mb_substr((string) $response->body(), 0, 500),
            ]);

            return ['ways' => [], 'nodes' => []];
        }

        $ways = [];
        $nodes = [];

        foreach (($response->json('elements') ?? []) as $element) {
            $type = $element['type'] ?? '';
            if ($type === 'way' && ! empty($element['geometry'])) {
                $geom = [];
                foreach ($element['geometry'] as $point) {
                    $geom[] = ['lat' => (float) $point['lat'], 'lng' => (float) $point['lon']];
                }
                $ways[] = [
                    'geometry' => $geom,
                    'tags' => $element['tags'] ?? [],
                ];
            } elseif ($type === 'node') {
                $nodes[] = [
                    'lat' => (float) ($element['lat'] ?? 0.0),
                    'lng' => (float) ($element['lon'] ?? 0.0),
                    'tags' => $element['tags'] ?? [],
                ];
            }
        }

        return ['ways' => $ways, 'nodes' => $nodes];
    }

    /**
     * Construye las señales a partir de los datos OSM reales devueltos.
     *
     * @param  array<int, array{lat: float, lng: float}>  $coordinates
     * @param  array<int, array{lat: float, lng: float}>  $points
     * @param  array{ways: array<int, array<string, mixed>>, nodes: array<int, array<string, mixed>>}  $elements
     * @return array<int, SafetySignal>
     */
    protected function buildSignals(array $coordinates, array $points, array $elements): array
    {
        $routeM = max($this->routeLength($coordinates), 1.0);
        $stepM = $routeM / max(1, count($points) - 1);

        $segments = [];
        foreach ($elements['ways'] as $idx => $way) {
            $geom = $way['geometry'];
            for ($i = 0; $i < count($geom) - 1; $i++) {
                $segments[] = [
                    'way' => $idx,
                    'a' => $geom[$i],
                    'b' => $geom[$i + 1],
                ];
            }
        }

        $samples = [];

        foreach ($points as $point) {
            $best = null;
            $bestDist = $this->radius;

            // Índice de fuerza bruta: corredores reales son cortos y el
            // conteo de segmentos por corredor suele ser < 2000.
            foreach ($segments as $segment) {
                $d = $this->distanceToSegment($point, $segment['a'], $segment['b']);
                if ($d < $bestDist) {
                    $bestDist = $d;
                    $best = $elements['ways'][$segment['way']]['tags'] ?? [];
                }
            }

            $samples[] = [
                'point' => $point,
                'tags' => $best ?? null,
                'distance_m' => $bestDist,
            ];
        }

        $roadType = [];
        $speed = [];
        $lighting = [];
        $surface = [];
        $cycleway = [];

        foreach ($samples as $sample) {
            $tags = $sample['tags'];
            $classedM = $tags === null ? 0.0 : $stepM;

            if ($tags !== null && isset($tags['highway'])) {
                $roadType['classes'][$tags['highway']] = ($roadType['classes'][$tags['highway']] ?? 0.0) + $stepM;
                $roadType['classified_m'] = ($roadType['classified_m'] ?? 0.0) + $stepM;
            }

            if ($tags !== null && ! empty($tags['maxspeed'])) {
                $score = $this->maxspeedScore($tags['maxspeed']);
                if ($score !== null) {
                    $speed['scored_m'] = ($speed['scored_m'] ?? 0.0) + $stepM;
                    $speed['total_score'] = ($speed['total_score'] ?? 0.0) + $stepM * $score;
                }
            }

            if ($tags !== null && ! empty($tags['lit'])) {
                $lit = strtolower((string) $tags['lit']);
                $score = (float) (config('safety.lighting_scores')[$lit] ?? null);
                if ($score !== null) {
                    $lighting['scored_m'] = ($lighting['scored_m'] ?? 0.0) + $stepM;
                    $lighting['total_score'] = ($lighting['total_score'] ?? 0.0) + $stepM * $score;
                    $lighting['by_value'][$lit] = ($lighting['by_value'][$lit] ?? 0.0) + $stepM;
                }
            }

            if ($tags !== null && ! empty($tags['surface'])) {
                $surf = strtolower((string) $tags['surface']);
                $score = (float) (config('safety.surface_scores')[$surf] ?? config('safety.surface_default_score', 60));
                $surface['scored_m'] = ($surface['scored_m'] ?? 0.0) + $stepM;
                $surface['total_score'] = ($surface['total_score'] ?? 0.0) + $stepM * $score;
                $surface['by_value'][$surf] = ($surface['by_value'][$surf] ?? 0.0) + $stepM;
            }

            $cyclepoint = $this->cyclewayScore($tags);
            if ($cyclepoint !== null) {
                $cycleway['scored_m'] = ($cycleway['scored_m'] ?? 0.0) + $stepM;
                $cycleway['total_score'] = ($cycleway['total_score'] ?? 0.0) + $stepM * $cyclepoint['score'];
                $cycleway['by_type'][$cyclepoint['type']] = ($cycleway['by_type'][$cyclepoint['type']] ?? 0.0) + $stepM;
            }
        }

        $signals = [];

        if (! empty($roadType['classified_m'])) {
            $score = 0.0;
            foreach ($roadType['classes'] as $class => $meters) {
                $score += $meters * $this->roadTypeScore($class);
            }
            $score /= $roadType['classified_m'];
            $signals[] = new SafetySignal(
                type: 'road_type',
                value: round($score, 1),
                coverage: $roadType['classified_m'] / $routeM,
                source: $this->name(),
                confidence: $roadType['classified_m'] / $routeM,
                metadata: [
                    'mapped_m' => round($roadType['classified_m'], 1),
                    'classes' => $this->metersToRounded($roadType['classes']),
                ],
            );
        }

        if (! empty($speed['scored_m'])) {
            $signals[] = new SafetySignal(
                type: 'speed',
                value: round($speed['total_score'] / $speed['scored_m'], 1),
                coverage: $speed['scored_m'] / $routeM,
                source: $this->name(),
                confidence: $speed['scored_m'] / $routeM,
                metadata: ['mapped_m' => round($speed['scored_m'], 1)],
            );
        }

        if (! empty($lighting['scored_m'])) {
            $signals[] = new SafetySignal(
                type: 'lighting',
                value: round($lighting['total_score'] / $lighting['scored_m'], 1),
                coverage: $lighting['scored_m'] / $routeM,
                source: $this->name(),
                confidence: $lighting['scored_m'] / $routeM,
                metadata: [
                    'mapped_m' => round($lighting['scored_m'], 1),
                    'by_value' => $this->metersToRounded($lighting['by_value'] ?? []),
                ],
            );
        }

        if (! empty($surface['scored_m'])) {
            $signals[] = new SafetySignal(
                type: 'surface',
                value: round($surface['total_score'] / $surface['scored_m'], 1),
                coverage: $surface['scored_m'] / $routeM,
                source: $this->name(),
                confidence: $surface['scored_m'] / $routeM,
                metadata: [
                    'mapped_m' => round($surface['scored_m'], 1),
                    'by_value' => $this->metersToRounded($surface['by_value'] ?? []),
                ],
            );
        }

        if (! empty($cycleway['scored_m'])) {
            $signals[] = new SafetySignal(
                type: 'cycleway',
                value: round($cycleway['total_score'] / $cycleway['scored_m'], 1),
                coverage: $cycleway['scored_m'] / $routeM,
                source: $this->name(),
                confidence: $cycleway['scored_m'] / $routeM,
                metadata: [
                    'mapped_m' => round($cycleway['scored_m'], 1),
                    'by_type' => $this->metersToRounded($cycleway['by_type'] ?? []),
                ],
            );
        }

        $crossings = $this->pointDensities($elements['nodes'], $points, $routeM);
        foreach ($crossings as $key => $info) {
            if ($info['count'] > 0) {
                $signals[] = new SafetySignal(
                    type: $key,
                    value: $info['value'],
                    coverage: 1.0,
                    source: $this->name(),
                    confidence: 1.0,
                    metadata: [
                        'count' => $info['count'],
                        'per_km' => round($info['per_km'], 2),
                    ],
                );
            }
        }

        return $signals;
    }

    /**
     * @param  array<string, mixed>|null  $tags
     * @return array{score: float, type: string}|null
     */
    protected function cyclewayScore(?array $tags): ?array
    {
        if ($tags === null) {
            return null;
        }

        $scores = config('safety.cycleway_scores', []);

        if (! empty($tags['cycleway'])) {
            $type = strtolower((string) $tags['cycleway']);
            if (array_key_exists($type, $scores)) {
                return ['score' => 100.0 * (float) $scores[$type], 'type' => $type];
            }
        }

        if (strtolower((string) ($tags['bicycle'] ?? '')) === 'designated') {
            return [
                'score' => 100.0 * (float) config('safety.cycleway_designated_score', 0.8),
                'type' => 'designated',
            ];
        }

        return null;
    }

    protected function roadTypeScore(string $highway): float
    {
        $row = config('safety.road_type_scores');

        return (float) ($row[$highway] ?? config('safety.road_type_default_score', 60));
    }

    /**
     * Convierte un maxspeed OSM ("50", "50 km/h", "walk", "none") a score o
     * null cuando no es interpretable (dato real pero no numérico medible).
     */
    protected function maxspeedScore(string $raw): ?float
    {
        $value = trim((string) $raw);
        $value = mb_strtolower($value);
        $value = preg_replace('/\s+km\/?h$/i', '', $value) ?? $value;

        if (is_numeric($value)) {
            $kmh = (float) $value;
        } else {
            return null;
        }

        $scores = config('safety.maxspeed_scores', []);

        $best = null;
        foreach ($scores as $limit => $score) {
            if ($kmh <= (float) $limit) {
                $best = (float) $score;
                break;
            }
        }

        $floorFrom = (float) config('safety.maxspeed_score_floor_from', 80);
        if ($kmh > $floorFrom) {
            $best = 0.0;
        }

        return $best;
    }

    /**
     * Densidad (por km) de nodos de cruce/calmado cerca de la ruta.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array{lat: float, lng: float}>  $points
     * @return array<string, array{count: int, per_km: float, value: float}>
     */
    protected function pointDensities(array $nodes, array $points, float $routeM): array
    {
        $result = [
            'crossings' => ['count' => 0, 'per_km' => 0.0, 'value' => 0.0],
            'traffic_calming' => ['count' => 0, 'per_km' => 0.0, 'value' => 0.0],
        ];

        foreach ($nodes as $node) {
            $tags = $node['tags'] ?? [];
            $isCrossing = isset($tags['highway']) && mb_strtolower((string) $tags['highway']) === 'crossing';
            $isCalming = isset($tags['traffic_calming']);

            if (! $isCrossing && ! $isCalming) {
                continue;
            }

            $near = false;
            foreach ($points as $point) {
                $d = $this->distanceMeters($point, $node);
                if ($d <= 45) {
                    $near = true;
                    break;
                }
            }

            if (! $near) {
                continue;
            }

            if ($isCrossing) {
                $result['crossings']['count']++;
            }
            if ($isCalming) {
                $result['traffic_calming']['count']++;
            }
        }

        $perKm = $routeM / 1000.0;

        $result['crossings']['per_km'] = $perKm > 0 ? $result['crossings']['count'] / $perKm : 0.0;
        // Más cruces sin protección reducen la fluidez/riesgo percibido.
        $densityX = $result['crossings']['per_km'];
        $result['crossings']['value'] = max(0.0, 100.0 - $densityX * 6);

        $result['traffic_calming']['per_km'] = $perKm > 0 ? $result['traffic_calming']['count'] / $perKm : 0.0;
        // El calmado de tráfico documentado es un factor positivo real.
        $result['traffic_calming']['value'] = min(100.0, 50.0 + $result['traffic_calming']['per_km'] * 12);

        return $result;
    }

    /**
     * @param  array{lat: float, lng: float}  $origin
     * @param  array{lat: float, lng: float}  $a
     * @param  array{lat: float, lng: float}  $b
     */
    protected function distanceToSegment(array $origin, array $a, array $b): float
    {
        $metersPerLat = 111320.0;
        $metersPerLng = 111320.0 * cos(deg2rad(($a['lat'] + $b['lat']) / 2.0));

        $ax = $a['lng'] * $metersPerLng;
        $ay = $a['lat'] * $metersPerLat;
        $bx = $b['lng'] * $metersPerLng;
        $by = $b['lat'] * $metersPerLat;
        $px = $origin['lng'] * $metersPerLng;
        $py = $origin['lat'] * $metersPerLat;

        $dx = $bx - $ax;
        $dy = $by - $ay;

        if ($dx === 0.0 && $dy === 0.0) {
            return sqrt(($px - $ax) ** 2 + ($py - $ay) ** 2);
        }

        $t = (($px - $ax) * $dx + ($py - $ay) * $dy) / ($dx * $dx + $dy * $dy);
        $t = max(0.0, min(1.0, $t));

        return sqrt((($ax + $t * $dx) - $px) ** 2 + (($ay + $t * $dy) - $py) ** 2);
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

        $margin = $this->radius / 111320.0;

        return [
            'minLat' => round($minLat - $margin, 2),
            'minLng' => round($minLng - $margin, 2),
            'maxLat' => round($maxLat + $margin, 2),
            'maxLng' => round($maxLng + $margin, 2),
        ];
    }

    /**
     * @param  array<int, mixed>  $coordinates
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
     * @param  array<int|string, float|int>  $meters
     * @return array<int|string, float>
     */
    protected function metersToRounded(array $meters): array
    {
        $out = [];
        foreach ($meters as $key => $value) {
            $out[$key] = round((float) $value, 1);
        }

        return $out;
    }
}