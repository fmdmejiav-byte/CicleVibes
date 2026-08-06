<?php

namespace App\Services\RouteProviders;

use App\Contracts\RouteProvider;
use App\Exceptions\RouteCalculationException;
use App\Support\Polyline;
use App\ValueObjects\RouteResult;
use Illuminate\Support\Facades\Http;

/**
 * Proveedor inicial: Google Directions API en modo bicicleta.
 *
 * En fases futuras este proveedor puede combinarse o sustituirse por un
 * algoritmo propio que pondere ciclorrutas oficiales, calles peligrosas,
 * reportes, huecos, inundaciones, tráfico, iluminación y pendientes.
 */
class GoogleDirectionsProvider implements RouteProvider
{
    private const ENDPOINT = 'https://maps.googleapis.com/maps/api/directions/json';

    public function getRoute(string $origen, string $destino): RouteResult
    {
        $response = Http::timeout(10)->acceptJson()->get(self::ENDPOINT, [
            'origin' => $origen,
            'destination' => $destino,
            'mode' => 'bicycling',
            'avoid' => 'highways',
            'language' => 'es',
            'key' => config('services.google_maps.key'),
        ]);

        if ($response->failed()) {
            throw new RouteCalculationException('El servicio de rutas no respondió correctamente.');
        }

        $data = $response->json();

        if (($data['status'] ?? null) !== 'OK' || empty($data['routes'][0])) {
            $message = $data['error_message'] ?? 'No se pudo calcular la ruta entre esos puntos.';
            throw new RouteCalculationException($message);
        }

        $route = $data['routes'][0];
        $leg = $route['legs'][0] ?? [];
        $polyline = $route['overview_polyline']['points'] ?? '';

        return new RouteResult(
            polyline: $polyline,
            distance: (int) ($leg['distance']['value'] ?? 0),
            duration: (int) ($leg['duration']['value'] ?? 0),
            coordinates: $polyline !== '' ? Polyline::decode($polyline) : [],
        );
    }
}
