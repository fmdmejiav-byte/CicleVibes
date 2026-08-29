<?php

namespace App\Services\Maps;

use Illuminate\Support\Facades\Http;

/**
 * Servicio de cálculo de rutas basado en OSRM.
 *
 * OSRM recibe coordenadas en formato [lng, lat] (longitud, latitud).
 * Devuelve la geometría de la ruta (polyline codificado por defecto)
 * junto con la distancia y la duración estimadas.
 *
 * La arquitectura permite sustituir OSRM por otro proveedor de routing
 * cambiando la URL y el perfil en las variables de entorno.
 */
class RoutingService
{
    protected string $baseUrl;

    protected string $profile;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.map.osrm_url'), '/');
        $this->profile = (string) config('services.map.osrm_profile', 'cycling');
    }

    /**
     * Calcula la ruta entre dos puntos (lat/lng).
     *
     * @return array<string, mixed>|null
     */
    public function route(array $origin, array $destination, string $overview = 'full'): ?array
    {
        $originLng = (float) $origin['lng'];
        $originLat = (float) $origin['lat'];
        $destLng = (float) $destination['lng'];
        $destLat = (float) $destination['lat'];

        $coordinates = "{$originLng},{$originLat};{$destLng},{$destLat}";

        $response = Http::acceptJson()
            ->timeout(20)
            ->get($this->baseUrl."/route/v1/{$this->profile}/{$coordinates}", [
                'overview' => $overview,
                'geometries' => 'polyline',
            ]);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        if (($data['code'] ?? '') !== 'Ok' || empty($data['routes'])) {
            return null;
        }

        $route = $data['routes'][0];

        return [
            'distance_m' => (float) ($route['distance'] ?? 0),
            'duration_seconds' => (float) ($route['duration'] ?? 0),
            'geometry' => $route['geometry'] ?? '',
            'waypoints' => $data['waypoints'] ?? [],
            'profile' => $this->profile,
        ];
    }

    /**
     * Devuelve la distancia en kilómetros.
     */
    public static function metersToKm(float $meters): float
    {
        return round($meters / 1000, 2);
    }

    /**
     * Devuelve la duración en formato legible (minutos).
     */
    public static function secondsToMinutes(float $seconds): int
    {
        return (int) round($seconds / 60);
    }
}
