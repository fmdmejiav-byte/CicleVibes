<?php

namespace App\Services\Routing;

use Illuminate\Support\Facades\Http;

/**
 * Driver de rutas para bicicletas basado en GraphHopper.
 *
 * GraphHopper permite perfiles específicos de bicicleta ('bike', 'bike2',
 * 'mtb'...) y prioriza infraestructura ciclista (ciclovías/ciclorutas) cuando
 * existe en OpenStreetMap.
 *
 * NOTA: el API alojado de GraphHopper requiere una API key real. Si
 * MAP_GRAPHHOPPER_KEY está vacío este driver devuelve null y la aplicación
 * debe seguir usando el driver OSRM (por defecto).
 */
class GraphHopperRoutingDriver implements BicycleRoutingDriver
{
    protected string $baseUrl;

    protected string $key;

    protected string $profile;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.map.graphhopper_url'), '/');
        $this->key = (string) config('services.map.graphhopper_key');
        $this->profile = (string) config('services.map.graphhopper_profile', 'bike');
    }

    public function name(): string
    {
        return 'graphhopper';
    }

    public function routes(array $origin, array $destination, ?int $alternatives = null): ?array
    {
        if ($this->key === '') {
            return null;
        }

        $alternatives = $alternatives ?? (int) config('services.map.alternatives_count', 3);
        $alternatives = min(max($alternatives, 1), 5);

        $response = Http::acceptJson()
            ->timeout(30)
            ->post($this->baseUrl.'/route', [
                'key' => $this->key,
                'profile' => $this->profile,
                'point' => ["{$origin['lat']},{$origin['lng']}", "{$destination['lat']},{$destination['lng']}"],
                'points_encoded' => false,
                'calc_points' => true,
                'instructions' => true,
                'alternative_route.max_paths' => $alternatives,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();

        if (empty($payload['paths'])) {
            return null;
        }

        $routes = [];
        $index = 0;
        foreach ($payload['paths'] as $path) {
            $index++;
            $routes[] = [
                'id' => $index,
                'distance_m' => (float) ($path['distance'] ?? 0),
                'duration_seconds' => (float) (($path['time'] ?? 0) / 1000),
                'distance_km' => round(((float) ($path['distance'] ?? 0)) / 1000, 2),
                'duration_min' => (int) round(((float) ($path['time'] ?? 0)) / 60000),
                'coordinates' => $this->normalizeCoordinates($path['points']['coordinates'] ?? []),
                'steps' => $this->buildSteps($path['instructions'] ?? []),
                'summary' => (string) ($path['description'] ?? ($path['descend'] ?? 'Ruta para bicicleta')),
                'profile' => $this->profile,
                'driver' => $this->name(),
            ];
        }

        return $routes;
    }

    /**
     * GraphHopper devuelve [[lng, lat, elevacion], ...] → normaliza a [[lat, lng], ...].
     *
     * @return array<int, array{0: float, 1: float}>
     */
    protected function normalizeCoordinates(array $points): array
    {
        $coordinates = [];
        foreach ($points as $point) {
            $lng = (float) ($point[0] ?? 0);
            $lat = (float) ($point[1] ?? 0);
            $coordinates[] = [$lat, $lng];
        }

        return $coordinates;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildSteps(array $instructions): array
    {
        $result = [];

        foreach ($instructions as $instr) {
            $result[] = [
                'instruction' => (string) ($instr['text'] ?? 'Continúa'),
                'distance_m' => (float) ($instr['distance'] ?? 0),
                'duration_seconds' => (float) (($instr['time'] ?? 0) / 1000),
            ];
        }

        return $result;
    }
}
