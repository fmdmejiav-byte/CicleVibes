<?php

namespace App\Services\Routing\Safety;

use App\Contracts\AccidentDataProvider;
use App\Contracts\SafetyDataProvider;
use App\Contracts\SafetyScoringService;
use App\Safety\SafetySignal;

/**
 * Safety Score real (0-100) de CicleVibes.
 *
 * Calcula la puntuación de seguridad de una ruta ÚNICAMENTE con datos
 * verificables: red local de ciclorrutas, OpenStreetMap (Overpass) y el
 * dataset oficial de siniestralidad configurado.
 *
 * Reglas de honestidad (no negociables):
 *  - Nunca inventa puntuaciones, accidentes, tráfico, iluminación o
 *    infraestructura. Cada factor puntúa sobre datos reales y documentados.
 *  - Si una fuente no aporta datos (API caída, región sin mapeo, dataset sin
 *    cobertura), el factor NO pesa en el score: solo reduce la confianza.
 *  - Si los datos disponibles no alcanzan el mínimo de factores o de
 *    confianza, el score es null y la UI muestra "Información de seguridad
 *    insuficiente" (preferible a un número poco respaldado).
 *  - La siniestralidad solo pesa cuando el dataset registra sectores
 *    críticos cerca de la ruta; con cero registros se excluye el factor.
 *
 * El resultado es determinista (memoizado por geometría) y de coste acotado
 * por el caché por corredor de los proveedores.
 */
class SafetyScoreService implements SafetyScoringService
{
    private const LABELS = [
        'infrastructure' => 'Infraestructura ciclista',
        'road_type' => 'Jerarquía de vía',
        'speed' => 'Velocidad máxima',
        'lighting' => 'Iluminación',
        'surface' => 'Superficie',
        'accidents' => 'Siniestralidad',
    ];

    protected SafetyDataProvider $osm;

    protected AccidentDataProvider $accidents;

    /** @var array<string, array<string, mixed>> */
    protected array $memo = [];

    public function __construct(
        ?SafetyDataProvider $osm = null,
        ?AccidentDataProvider $accidents = null,
    ) {
        $this->osm = $osm ?? new OpenStreetMapSafetyDataProvider();
        $this->accidents = $accidents ?? new SocrataAccidentDataProvider();
    }

    public function score(array $route): ?float
    {
        return $this->assessment($route)['score'] ?? null;
    }

    public function assessment(array $route): array
    {
        $key = $this->memoKey($route);

        return $this->memo[$key] ??= $this->compute($route);
    }

    public function signals(array $route): array
    {
        $map = [];
        foreach (($this->assessment($route)['signals'] ?? []) as $signal) {
            $map[$signal['type']] = $signal;
        }

        // Compatibilidad con la señal real de infraestructura de fase 1.
        $map['cicloruta_coverage_pct'] = (int) ($route['cicloruta_coverage_pct'] ?? 0);
        $map['cicloruta_safety_index'] = isset($route['cicloruta_safety_index'])
            ? round((float) $route['cicloruta_safety_index'], 3)
            : null;
        $map['cicloruta_matched_m'] = isset($route['cicloruta_matched_m'])
            ? round((float) $route['cicloruta_matched_m'], 1)
            : null;

        return $map;
    }

    public function sources(): array
    {
        return [
            'infrastructure' => [
                'available' => true,
                'status' => 'Real: red local de ciclorrutas (cobertura e índice de infraestructura) y/o carriles bici de OpenStreetMap.',
            ],
            'bicycle_infrastructure' => [
                'available' => true,
                'status' => 'Real: red local de ciclorrutas (cobertura e índice de infraestructura).',
            ],
            'road_type' => [
                'available' => true,
                'status' => 'Real: jerarquía de vía (highway) documentada en OpenStreetMap.',
            ],
            'speed' => [
                'available' => true,
                'status' => 'Real: velocidad máxima (maxspeed) documentada en OpenStreetMap.',
            ],
            'lighting' => [
                'available' => true,
                'status' => 'Real: iluminación (lit) documentada en OpenStreetMap.',
            ],
            'surface' => [
                'available' => true,
                'status' => 'Real: superficie (surface) documentada en OpenStreetMap.',
            ],
            'accidents' => [
                'available' => (bool) config('safety.accidents.enabled', false),
                'status' => 'Real: '.((string) (config('safety.accidents.socrata.source.name') ?? 'dataset oficial de siniestralidad')).'.',
            ],
            'community_reports' => [
                'available' => false,
                'status' => 'Fase 3: reportes comunitarios de seguridad (no implementados en esta fase).',
            ],
            'traffic' => [
                'available' => false,
                'status' => 'Fase 3: tráfico (no implementado en esta fase).',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $route
     */
    protected function memoKey(array $route): string
    {
        $coords = $route['coordinates'] ?? [];

        return sha1(json_encode($coords) ?: 'empty');
    }

    /**
     * @param  array<string, mixed>  $route
     * @return array{score: float|null, confidence: float, confidence_label: string, explanation: string|null, signals: array<int, array<string, mixed>>, sources: array<string, mixed>, unit: string, warnings: array<int, string>}
     */
    protected function compute(array $route): array
    {
        $unit = (string) config('safety.unit', '0-100');

        if (! (bool) config('safety.enabled', true)) {
            return $this->insufficient(
                'Safety Score desactivado por configuración (SAFETY_ENABLED=false). Se prefiere no publicar puntuaciones sin análisis.',
                0.0,
                [],
                $unit,
                ['Safety Score desactivado por configuración.']
            );
        }

        $coordinates = $route['coordinates'] ?? [];
        if (! is_array($coordinates) || count($coordinates) < 2) {
            return $this->insufficient(
                'Información de seguridad insuficiente: la ruta no tiene geometría válida para analizar.',
                0.0,
                [],
                $unit,
                ['Geometría de ruta ausente o incompleta.']
            );
        }

        $providerSignals = array_merge(
            $this->osm->signals($coordinates),
            $this->accidents->signals($coordinates)
        );

        $byType = [];
        foreach ($providerSignals as $signal) {
            $byType[$signal->type] = $signal;
        }

        $infra = $this->infrastructureFactor($route, $byType['cycleway'] ?? null);

        $available = [];
        $missing = [];
        $totalWeight = 0.0;

        foreach (config('safety.factors', []) as $key => $cfg) {
            if (! ($cfg['enabled'] ?? false) || (float) ($cfg['weight'] ?? 0) <= 0) {
                continue;
            }

            if ($key === 'accidents' && ! (bool) config('safety.accidents.enabled', false)) {
                // La fuente de siniestralidad está deshabilitada por
                // configuración: NO diluye la confianza como si "faltaran
                // datos". Simplemente no participa en el análisis.
                continue;
            }

            $totalWeight += (float) $cfg['weight'];

            if ($key === 'infrastructure') {
                $factor = $infra;
            } else {
                $factor = $this->factorFromSignal($key, $byType[$key] ?? null);
            }

            if ($factor === null || $factor['coverage'] <= 0.0) {
                $missing[] = Self::LABELS[$key] ?? $key;
                continue;
            }

            $factor['key'] = $key;
            $factor['label'] = Self::LABELS[$key] ?? $key;
            $available[$key] = $factor;
        }

        $availableSignals = [];
        foreach ($available as $factor) {
            $availableSignals[] = [
                'type' => $factor['key'],
                'name' => $factor['label'],
                'score' => (float) round($factor['score'], 1),
                'coverage' => (float) round($factor['coverage'], 4),
                'source' => $factor['source'],
                'metadata' => $factor['metadata'] ?? [],
            ];
        }

        $minFactors = max(1, (int) config('safety.min_factors', 2));
        $minConfidence = max(0.0, min(1.0, (float) config('safety.min_confidence', 0.25)));

        $weightedCoverage = 0.0;
        $availableWeight = 0.0;
        $weightedScore = 0.0;

        foreach ($available as $factor) {
            $weight = (float) config("safety.factors.{$factor['key']}.weight", 0);
            $weightedCoverage += $weight * $factor['coverage'];
            $availableWeight += $weight;
            $weightedScore += $weight * $factor['score'];
        }

        $confidence = $totalWeight > 0 ? $weightedCoverage / $totalWeight : 0.0;
        $confidence = max(0.0, min(1.0, $confidence));

        if (count($available) < $minFactors) {
            $why = implode(', ', $missing);
            return $this->insufficient(
                "Información de seguridad insuficiente: solo se dispusieron de datos reales de ".count($available)." factor(es)".($why !== '' ? " (faltan: {$why})" : '').". Se requieren al menos {$minFactors}.",
                $confidence,
                $availableSignals,
                $unit,
                $missing === [] ? [] : ['Sin datos reales de: '.implode(', ', $missing).'.'],
                $confidence
            );
        }

        if ($confidence < $minConfidence) {
            return $this->insufficient(
                'Información de seguridad insuficiente: la confianza del análisis ('.round($confidence, 2).') está por debajo del mínimo exigido ('.$minConfidence.').',
                $confidence,
                $availableSignals,
                $unit,
                $missing === [] ? [] : ['Sin datos reales de: '.implode(', ', $missing).'.'],
                $confidence
            );
        }

        $score = $availableWeight > 0 ? $weightedScore / $availableWeight : null;
        $score = $score === null ? null : max(0.0, min(100.0, $score));

        $explanation = $this->explanation($route, $available, $missing, $score);

        return [
            'score' => $score === null ? null : (float) round($score),
            'confidence' => $confidence,
            'confidence_label' => $this->confidenceLabel($confidence),
            'explanation' => $explanation,
            'signals' => $availableSignals,
            'sources' => $this->sources(),
            'unit' => $unit,
            'warnings' => $missing === [] ? [] : ['Sin datos reales de: '.implode(', ', $missing).'.'],
        ];
    }

    /**
     * Factor de infraestructura: índice real de la red local de ciclorrutas
     * y/o carril bici documentado en OpenStreetMap (cycleway). Solo se
     * puntúa si existe alguno de los dos datos reales.
     *
     * @param  array<string, mixed>  $route
     */
    protected function infrastructureFactor(array $route, ?SafetySignal $cycleway): ?array
    {
        $localScore = null;
        $localCoverage = 0.0;

        if (isset($route['cicloruta_safety_index']) && is_numeric($route['cicloruta_safety_index'])) {
            $localScore = max(0.0, min(100.0, (float) $route['cicloruta_safety_index'] * 100));
            $localCoverage = 1.0;
        }

        $osmScore = $cycleway?->value;
        $osmCoverage = $cycleway?->coverage ?? 0.0;

        $score = null;
        if ($localScore !== null && $osmScore !== null) {
            $score = max($localScore, $osmScore);
        } elseif ($localScore !== null) {
            $score = $localScore;
        } elseif ($osmScore !== null) {
            $score = $osmScore;
        }

        if ($score === null) {
            return null;
        }

        $coverage = min(1.0, max($localCoverage, $osmCoverage));
        if ($coverage <= 0.0) {
            return null;
        }

        return [
            'score' => $score,
            'coverage' => $coverage,
            'source' => $localCoverage > 0 ? 'Red de ciclorrutas local' : $cycleway?->source ?? 'OpenStreetMap',
            'metadata' => [
                'cicloruta_safety_index' => isset($route['cicloruta_safety_index'])
                    ? round((float) $route['cicloruta_safety_index'], 3)
                    : null,
                'cicloruta_coverage_pct' => (int) ($route['cicloruta_coverage_pct'] ?? 0),
                'osm_cycleway_m' => $cycleway?->metadata['mapped_m'] ?? null,
            ],
        ];
    }

    /**
     * Convierte una señal del proveedor en el factor equivalente (score real
     * y cobertura real). Solo aplica cuando existe señal con cobertura.
     */
    protected function factorFromSignal(string $key, ?SafetySignal $signal): ?array
    {
        if ($signal === null || $signal->coverage <= 0.0) {
            return null;
        }

        return [
            'score' => max(0.0, min(100.0, $signal->value)),
            'coverage' => $signal->coverage,
            'source' => $signal->source,
            'metadata' => $signal->metadata,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $available
     * @param  array<int, string>  $missing
     */
    protected function explanation(array $route, array $available, array $missing, ?float $score): string
    {
        $parts = [];
        foreach ($available as $factor) {
            $parts[] = sprintf(
                '%s: %d/100',
                $factor['label'],
                (int) round($factor['score'])
            );
        }

        $text = $score === null
            ? 'Información de seguridad insuficiente.'
            : sprintf('Safety Score estimado: %d/100.', (int) round($score));

        if ($parts !== []) {
            $text .= ' '.implode(' · ', $parts).'.';
        }

        if ($missing !== []) {
            $text .= ' Sin datos reales de: '.implode(', ', $missing).'.';
        }

        return $text;
    }

    /**
     * @param  array<int, array<string, mixed>>  $signals
     * @param  array<int, string>  $warnings
     * @return array{score: null, confidence: float, confidence_label: string, explanation: string|null, signals: array<int, array<string, mixed>>, sources: array<string, mixed>, unit: string, warnings: array<int, string>}
     */
    protected function insufficient(
        string $explanation,
        float $confidence,
        array $signals,
        string $unit,
        array $warnings = [],
        ?float $overrideConfidence = null,
    ): array {
        return [
            'score' => null,
            'confidence' => $overrideConfidence ?? 0.0,
            'confidence_label' => $this->confidenceLabel($overrideConfidence ?? $confidence),
            'explanation' => $explanation,
            'signals' => $signals,
            'sources' => $this->sources(),
            'unit' => $unit,
            'warnings' => $warnings,
        ];
    }

    protected function confidenceLabel(float $confidence): string
    {
        $conf = config('safety.confidence', []);
        $labels = config('safety.labels', []);

        if ($confidence >= (float) ($conf['high'] ?? 0.7)) {
            return (string) ($labels['high'] ?? 'Alta');
        }
        if ($confidence >= (float) ($conf['medium'] ?? 0.45)) {
            return (string) ($labels['medium'] ?? 'Media');
        }

        return (string) ($labels['low'] ?? 'Baja');
    }
}