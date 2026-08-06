<?php

namespace App\Contracts;

use App\ValueObjects\RouteResult;

/**
 * Proveedor de rutas para ciclistas.
 *
 * Implementaciones futuras pueden usar Google Directions, ciclorrutas
 * oficiales, reportes de usuarios o un algoritmo propio con pesos.
 */
interface RouteProvider
{
    public function getRoute(string $origen, string $destino): RouteResult;
}
