<?php

namespace App\ValueObjects;

/**
 * Resultado del cálculo de una ruta, independiente del proveedor que la genere.
 */
class RouteResult
{
    public function __construct(
        public readonly string $polyline,
        public readonly int $distance,
        public readonly int $duration,
        public readonly array $coordinates,
    ) {
    }

    public function distanceInKilometers(): float
    {
        return round($this->distance / 1000, 2);
    }

    public function durationInMinutes(): int
    {
        return (int) round($this->duration / 60);
    }
}
