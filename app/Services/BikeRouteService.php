<?php

namespace App\Services;

use App\Contracts\RouteProvider;
use App\ValueObjects\RouteResult;

/**
 * Punto de entrada para calcular rutas seguras en bicicleta.
 *
 * Esta clase solo orquesta: delega en el RouteProvider activo. Así, en el
 * futuro el algoritmo propio (ciclorrutas oficiales + reportes + pesos)
 * puede implementarse sin tocar la interfaz, el controlador ni las vistas.
 */
class BikeRouteService
{
    public function __construct(
        private readonly RouteProvider $provider,
    ) {
    }

    public function calculate(string $origen, string $destino): RouteResult
    {
        return $this->provider->getRoute($origen, $destino);
    }
}
