<?php

namespace App\Services\Routing;

use Illuminate\Support\Facades\Http;

/**
 * Driver de rutas para bicicletas basado en OSRM con su perfil 'cycling'.
 *
 * OSRM recibe coordenadas en formato [lng, lat] y devuelve la geometría
 * como polyline codificado. Este driver normaliza la respuesta a un formato
 * común ([lat, lng]) para que el resto de la aplicación no dependa del
 * proveedor concreto.
 */
class OsrmRoutingDriver implements BicycleRoutingDriver
{
    protected string $baseUrl;

    protected string $profile;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.map.osrm_url'), '/');
        $this->profile = (string) config('services.map.osrm_profile', 'cycling');
    }

    public function name(): string
    {
        return 'osrm';
    }

    public function routes(array $origin, array $destination, ?int $alternatives = null): ?array
    {
        $alternatives = $alternatives ?? (int) config('services.map.osrm_alternatives', 2);
        $alternatives = min(max($alternatives, 0), 3);

        $coordinates = "{$origin['lng']},{$origin['lat']};{$destination['lng']},{$destination['lat']}";

        $response = Http::acceptJson()
            ->timeout(25)
            ->get($this->baseUrl."/route/v1/{$this->profile}/{$coordinates}", [
                'overview' => 'full',
                'geometries' => 'polyline',
                'steps' => 'true',
                'alternatives' => $alternatives,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();

        if (($payload['code'] ?? '') !== 'Ok' || empty($payload['routes'])) {
            return null;
        }

        $routes = [];
        foreach ($payload['routes'] as $index => $raw) {
            $legs = $raw['legs'][0] ?? null;
            $steps = $legs['steps'] ?? [];
            $route = [
                'id' => $index + 1,
                'distance_m' => (float) ($raw['distance'] ?? 0),
                'duration_seconds' => (float) ($raw['duration'] ?? 0),
                'distance_km' => round(((float) ($raw['distance'] ?? 0)) / 1000, 2),
                'duration_min' => (int) round(((float) ($raw['duration'] ?? 0)) / 60),
                'coordinates' => $this->decodePolyline($raw['geometry'] ?? ''),
                'steps' => $this->buildSteps($steps),
                'summary' => $this->summarize($steps),
                'profile' => $this->profile,
                'driver' => $this->name(),
                // Datos de elevación: el OSRM público (router.project-osrm.org)
                // no entrega elevación en 'overview=full', así que estos campos
                // quedan a null (nunca se simulan). Si un servidor OSRM propio
                // con datos de elevación los incluyera, podrían poblarse aquí.
                'elevation_available' => false,
                'elevations' => null,
                'ascent_m' => null,
                'descent_m' => null,
            ];
            $routes[] = $this->coherentDuration($route);
        }

        return $routes;
    }

    /**
     * Ajusta la duración de una ruta para que sea coherente con su distancia real.
     *
     * OSRM con el perfil 'cycling' ya estima el tiempo sobre la geometría del
     * recorrido con su modelo de velocidad para bicicleta, que es la mejor
     * opción disponible en la arquitectura actual. No obstante, en condiciones
     * urbanas su heurística puede ser optimista y mostrar una ruta larga con
     * un tiempo irrealmente corto. Para evitarlo: si la duración devuelta
     * implicara una velocidad media por encima de bike_route_max_expected_speed_kmh
     * (improbable en ciudad), se recalcula a partir de la distancia REAL de la
     * geometría con la velocidad media de desplazamiento (bike_avg_speed_kmh).
     *
     * @param  array<string, mixed>  $route
     * @return array<string, mixed>
     */
    protected function coherentDuration(array $route): array
    {
        $distance = (float) ($route['distance_m'] ?? 0);
        if ($distance <= 0) {
            return $route;
        }

        $impliedSpeed = (float) ($route['duration_seconds'] ?? 0) > 0
            ? $distance / (float) $route['duration_seconds'] * 3.6
            : PHP_FLOAT_MAX;

        $maxSpeed = (float) config('services.map.bike_route_max_expected_speed_kmh', 25);

        if ($impliedSpeed <= $maxSpeed) {
            return $route;
        }

        $avgSpeed = (float) config('services.map.bike_avg_speed_kmh', 15);
        $seconds = $distance / ($avgSpeed * 1000 / 3600);

        $route['duration_seconds'] = round($seconds, 1);
        $route['duration_min'] = (int) round($seconds / 60);

        return $route;
    }

    /**
     * Convierte los pasos (maneuvers) de OSRM en instrucciones legibles.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildSteps(array $steps): array
    {
        $result = [];

        foreach ($steps as $step) {
            $maneuver = $step['maneuver'] ?? [];
            $name = trim((string) ($step['name'] ?? ''));
            $type = (string) ($maneuver['type'] ?? '');
            $modifier = (string) ($maneuver['modifier'] ?? '');

            $instruction = $this->phraseInstruction($type, $modifier, $name);

            $result[] = [
                'instruction' => $instruction,
                'distance_m' => (float) ($step['distance'] ?? 0),
                'duration_seconds' => (float) ($step['duration'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Construye un texto de instrucción a partir del tipo de maniobra OSRM.
     */
    protected function phraseInstruction(string $type, string $modifier, string $name): string
    {
        $direction = [
            'left' => 'a la izquierda',
            'right' => 'a la derecha',
            'sharp left' => 'pronunciadamente a la izquierda',
            'sharp right' => 'pronunciadamente a la derecha',
            'slight left' => 'ligeramente a la izquierda',
            'slight right' => 'ligeramente a la derecha',
            'straight' => 'de frente',
            'uturn' => 'en U',
        ];

        $dir = $direction[$modifier] ?? '';

        return match ($type) {
            'depart' => 'Avanza hacia '.($name !== '' ? $name : 'tu destino'),
            'arrive' => 'Has llegado a tu destino',
            'turn', 'new name' => 'Gira '.($dir !== '' ? $dir : '').($name !== '' ? " por {$name}" : ''),
            'continue', 'merge', 'on ramp', 'off ramp', 'fork' => 'Continúa '.($dir !== '' ? $dir : '').($name !== '' ? " por {$name}" : ''),
            'roundabout', 'rotary' => 'Toma la rotonda y sal'.($dir !== '' ? " {$dir}" : ''),
            'end of road' => 'Al final de la vía, gira '.($dir !== '' ? $dir : ''),
            'use lane' => 'Mantén el carril',
            'notification' => 'Sigue las indicaciones',
            default => ($name !== '' ? "Continúa por {$name}" : 'Continúa'),
        };
    }

    protected function summarize(array $steps): string
    {
        $parts = [];
        $count = 0;
        foreach ($steps as $step) {
            $name = trim((string) ($step['name'] ?? ''));
            if ($name !== '' && ! in_array($name, $parts, true)) {
                $parts[] = $name;
                $count++;
            }
            if ($count >= 4) {
                break;
            }
        }

        return $parts !== [] ? implode(' · ', $parts) : 'Ruta para bicicleta';
    }

    /**
     * Decodifica un polyline (precisión 5, orden lat/lng) a [[lat, lng], ...].
     *
     * @return array<int, array{0: float, 1: float}>
     */
    protected function decodePolyline(string $encoded): array
    {
        $index = 0;
        $lat = 0;
        $lng = 0;
        $coordinates = [];
        $length = strlen($encoded);

        while ($index < $length) {
            $result = 0;
            $shift = 0;

            do {
                $byte = ord($encoded[$index++]) - 63;
                $result |= ($byte & 0x1F) << $shift;
                $shift += 5;
            } while ($byte >= 0x20);
            $lat += $result & 1 ? ~($result >> 1) : ($result >> 1);

            $result = 0;
            $shift = 0;
            do {
                $byte = ord($encoded[$index++]) - 63;
                $result |= ($byte & 0x1F) << $shift;
                $shift += 5;
            } while ($byte >= 0x20);
            $lng += $result & 1 ? ~($result >> 1) : ($result >> 1);

            $coordinates[] = [$lat / 100000, $lng / 100000];
        }

        return $coordinates;
    }
}
