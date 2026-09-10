<?php

namespace App\Services\Routing\Safety;

use App\Contracts\SafetyScoringService;

/**
 * Implementación del Safety Score basada en infraestructura ciclista real.
 *
 * FASE 1: score() devuelve null SIEMPRE. No hay todavía datos reales
 * (siniestralidad, alumbrado, tráfico, reportes comunitarios) que permitan
 * una puntuación de seguridad verificable, así que no se inventa ninguna.
 *
 * Lo que sí aporta es la señal real de infraestructura ciclista medida sobre
 * la red local (cobertura, metros coincidentes e índice de infraestructura),
 * que es el único dato verificable disponible en esta fase y que el perfil
 * SAFEST utiliza para ordenar candidatas hasta que exista el Safety Score
 * completo de la fase 2.
 */
class InfrastructureSafetyScoringService implements SafetyScoringService
{
    public function score(array $route): ?float
    {
        return null;
    }

    public function assessment(array $route): array
    {
        return [
            'score' => null,
            'confidence' => 0.0,
            'explanation' => 'Información de seguridad insuficiente: esta implementación (fase 1) aún no dispone de las fuentes reales del Safety Score completo.',
            'signals' => [],
            'sources' => $this->sources(),
            'unit' => '0-100',
            'warnings' => ['Safety Score completo pendiente de integración de fuentes reales.'],
        ];
    }

    public function signals(array $route): array
    {
        return [
            'cicloruta_coverage_pct' => (int) ($route['cicloruta_coverage_pct'] ?? 0),
            'cicloruta_safety_index' => isset($route['cicloruta_safety_index'])
                ? round((float) $route['cicloruta_safety_index'], 3)
                : null,
            'cicloruta_matched_m' => isset($route['cicloruta_matched_m'])
                ? round((float) $route['cicloruta_matched_m'], 1)
                : null,
        ];
    }

    public function sources(): array
    {
        return [
            'bicycle_infrastructure' => [
                'available' => true,
                'status' => 'Real: red de ciclorrutas local (cobertura e índice de infraestructura).',
            ],
            'road_type' => [
                'available' => false,
                'status' => 'Fase 2: tipo y jerarquía de vía desde OpenStreetMap.',
            ],
            'community_reports' => [
                'available' => false,
                'status' => 'Fase 2: reportes comunitarios de seguridad.',
            ],
            'accidents' => [
                'available' => false,
                'status' => 'Fase 2: siniestralidad.',
            ],
            'lighting' => [
                'available' => false,
                'status' => 'Fase 2: iluminación.',
            ],
            'traffic' => [
                'available' => false,
                'status' => 'Fase 2: tráfico.',
            ],
        ];
    }
}