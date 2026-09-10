<?php

namespace App\Contracts;

/**
 * Abstracción de puntuación de seguridad para las rutas de CicleVibes.
 *
 * Fase 1 (rutas inteligentes): la arquitectura queda preparada para que la
 * fase 2 integre el Safety Score completo alimentado por datos reales:
 * infraestructura ciclista, tipo de vía, reportes comunitarios, siniestralidad,
 * iluminación, tráfico y otras fuentes verificables.
 *
 * En esta fase la implementación activa devuelve score() === null porque aún
 * no existen datos reales suficientes para asignar una puntuación de seguridad
 * verificable. El perfil SAFEST se ordena mientras tanto con los datos reales
 * disponibles (cobertura de infraestructura ciclista), nunca con valores
 * inventados.
 */
interface SafetyScoringService
{
    /**
     * Puntuación de seguridad real (0..100) para una ruta, o null si no hay
     * datos reales suficientes en este momento.
     *
     * @param  array<string, mixed>  $route
     */
    public function score(array $route): ?float;

    /**
     * Evaluación completa de seguridad de la ruta a partir de datos reales.
     *
     * Siempre devuelve una estructura (nunca null): cuando no hay datos
     * suficientes, score se deja en null y explanation lo comunica.
     *
     * Estructura:
     *  - score: 0..100 o null (información insuficiente).
     *  - confidence: 0..1 (cobertura ponderada de los datos reales usados).
     *  - explanation: frase en español que explica el porqué del score, o
     *    la causa de por qué no hay score (solo usa datos reales).
     *  - signals: factores evaluados (lista de arrays con type, score,
     *    coverage, source, metadata) sobre los que se basó el cálculo.
     *  - sources: mapa de fuentes y su estado (idéntico a sources()).
     *  - unit: magnitud del score ('0-100').
     *  - warnings: avisos honestos (p. ej. "sin señal de iluminación").
     *
     * @param  array<string, mixed>  $route
     * @return array{score: float|null, confidence: float, explanation: string|null, signals: array<int, array<string, mixed>>, sources: array<string, mixed>, unit: string, warnings: array<int, string>}
     */
    public function assessment(array $route): array;

    /**
     * Señales reales disponibles en la ruta (métricas, no puntuaciones).
     *
     * @param  array<string, mixed>  $route
     * @return array<string, mixed>
     */
    public function signals(array $route): array;

    /**
     * Fuentes de datos del Safety Score y su estado en esta fase.
     *
     * @return array<string, array{available: bool, status: string}>
     */
    public function sources(): array;
}