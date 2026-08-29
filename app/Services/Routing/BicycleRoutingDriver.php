<?php

namespace App\Services\Routing;

/**
 * Contrato para los motores de cálculo de rutas para bicicletas.
 *
 * Un driver recibe un origen y un destino en lat/lng y devuelve una lista
 * de rutas alternativas normalizadas (misma estructura para todos los
 * proveedores), o null si no pudo calcular ninguna ruta.
 *
 * Estructura de cada ruta devuelta:
 *  [
 *    'id'               => int,
 *    'distance_m'       => float,
 *    'duration_seconds' => float,
 *    'distance_km'      => float,
 *    'duration_min'     => int,
 *    'coordinates'      => [[lat, lng], ...],
 *    'steps'            => [ ['instruction'=>string, 'distance_m'=>float, 'duration_seconds'=>float], ... ],
 *    'summary'          => string,
 *    'profile'          => string,
 *    'driver'           => string,
 *  ]
 */
interface BicycleRoutingDriver
{
    /**
     * @return array<int, array<string, mixed>>|null Lista de rutas alternativas.
     */
    public function routes(array $origin, array $destination, ?int $alternatives = null): ?array;

    /**
     * Nombre identificativo del driver ('osrm', 'graphhopper', ...).
     */
    public function name(): string;
}
