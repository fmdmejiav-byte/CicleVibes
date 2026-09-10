<?php

namespace App\Enums;

/**
 * Perfiles del planificador inteligente de rutas de CicleVibes.
 *
 * Cada perfil representa un criterio de prioridad distinto al elegir la mejor
 * ruta entre las candidatas REALES calculadas por el motor común de rutas.
 *
 * Regla de negocio: la capa de criterios NUNCA inventa datos. Cada perfil
 * ordena las candidatas únicamente con los campos reales disponibles:
 *  - distancia y duración reales del motor de rutas;
 *  - elevación, si el proveedor activo la entrega (GrafHopper con perfil de
 *    elevación), de lo contrario null y el perfil lo documenta;
 *  - cobertura de infraestructura ciclista real (red local de CicleVibes);
 *  - Safety Score: preparado para la fase 2 (todavía no hay datos reales).
 */
enum RouteProfile: string
{
    case Fastest = 'fastest';
    case Shortest = 'shortest';
    case Easiest = 'easiest';
    case Scenic = 'scenic';
    case Safest = 'safest';

    /**
     * Nombre corto mostrable en la interfaz.
     */
    public function label(): string
    {
        return match ($this) {
            self::Fastest => 'Más rápida',
            self::Shortest => 'Más corta',
            self::Easiest => 'Menor esfuerzo',
            self::Scenic => 'Más tranquila',
            self::Safest => 'Más segura',
        };
    }

    /**
     * Emoji de la interfaz.
     */
    public function emoji(): string
    {
        return match ($this) {
            self::Fastest => '🚴',
            self::Shortest => '📏',
            self::Easiest => '⛰️',
            self::Scenic => '🌳',
            self::Safest => '🛡️',
        };
    }

    /**
     * Descripción honesta del criterio y de los datos reales que usa.
     */
    public function description(): string
    {
        return match ($this) {
            self::Fastest => 'Prioriza la menor duración real sobre vías aptas para bicicleta.',
            self::Shortest => 'Prioriza la menor distancia real, respetando las restricciones de bicicleta del motor.',
            self::Easiest => 'Prioriza menos desnivel positivo. Sin datos reales de elevación del proveedor, usa la ruta ciclista del motor (documenta la limitación).',
            self::Scenic => 'Prioriza, cuando existen, los recorridos con más infraestructura ciclista y menor exposición al tráfico.',
            self::Safest => 'Preparado para el Safety Score en la fase 2 (infraestructura, tipo de vía, reportes, siniestralidad, iluminación, tráfico). En esta fase usa únicamente datos reales de infraestructura ciclista.',
        };
    }

    /**
     * Todas las claves admitidas.
     *
     * @return array<int, string>
     */
    public static function allowedKeys(): array
    {
        return array_map(fn (self $profile) => $profile->value, self::cases());
    }

    /**
     * Normaliza una entrada (string|self) a instancia o null.
     */
    public static function tryFromMixed(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_string($value)) {
            $candidate = strtolower(trim($value));

            return self::tryFrom($candidate);
        }

        return null;
    }
}