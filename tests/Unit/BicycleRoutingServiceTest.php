<?php

use App\Services\Maps\CiclorutaService;
use App\Services\Routing\BicycleRoutingDriver;
use App\Services\Routing\BicycleRoutingService;

/**
 * Driver OSRM falso: devuelve rutas fijas independientemente de los destinos,
 * ideal para aislar la lógica de ordenación, no la del proveedor.
 */
class CyclingFakeDriver implements BicycleRoutingDriver
{
    public function __construct(protected array $fixedRoutes) {}

    public function routes(array $origin, array $destination, ?int $alternatives = null): ?array
    {
        return $this->fixedRoutes;
    }

    public function name(): string
    {
        return 'fake';
    }
}

/**
 * Rutas OSRM base expresadas con la misma estructura que devuelve el driver.
 *
 * @return array<int, array<string, mixed>>
 */
function cyclingBaseRoute(array $overrides): array
{
    $route = array_merge([
        'distance_m' => 1000,
        'duration_seconds' => 100,
        'distance_km' => 1.0,
        'duration_min' => 2,
        'steps' => [],
        'summary' => 'Directa',
        'profile' => 'cicle',
        'driver' => 'osrm',
    ], $overrides);

    $route['distance_m'] = (float) $route['distance_m'];
    $route['duration_seconds'] = (float) $route['duration_seconds'];

    return $route;
}

test('con priorizar, una ruta algo más larga pero sobre ciclobanda queda por delante de la rápida por calle', function () {
    $service = new BicycleRoutingService(
        new CyclingFakeDriver([
            // Ruta rápida por calles: 1.0 km / 100 s, lejos de la red.
            cyclingBaseRoute([
                'distance_m' => 1000,
                'duration_seconds' => 100,
                'coordinates' => [[11.02, -74.78], [11.025, -74.78]],
            ]),
            // Ruta sobre la "Carrera 47" del fixture (ciclobanda): 1.1 km / 130 s.
            cyclingBaseRoute([
                'distance_m' => 1100,
                'duration_seconds' => 130,
                'coordinates' => [[10.96, -74.81], [10.962, -74.808], [10.964, -74.806], [10.966, -74.804]],
            ]),
        ]),
        new CiclorutaService(__DIR__.'/../Fixtures/ciclorutas-test.geojson')
    );

    // Destino sobre la "Ciclovía Aislada": sin candidata por la red completa,
    // así que se prueba la ordenación ponderada de las OSRM únicamente.
    $routes = $service->routesViaCiclorutas(
        ['lat' => 10.962, 'lng' => -74.808],
        ['lat' => 10.963, 'lng' => -74.764]
    );

    expect($routes)->toHaveCount(2)
        ->and($routes[0]['distance_m'])->toBe(1100.0)
        ->and($routes[0]['cicloruta_coverage_pct'])->toBeGreaterThan(0)
        ->and($routes[1]['distance_m'])->toBe(1000.0)
        ->and($routes[1]['cicloruta_coverage_pct'])->toBe(0);
});

test('la ordenación ponderada respeta el equilibrio cuando la diferencia es pequeña', function () {
    $service = new BicycleRoutingService(
        new CyclingFakeDriver([
            // Ruta rápida por calles (sin infra) vs. ruta más larga sobre red.
            cyclingBaseRoute([
                'distance_m' => 1000,
                'duration_seconds' => 60,
                'coordinates' => [[11.02, -74.78], [11.025, -74.78]],
            ]),
            cyclingBaseRoute([
                'distance_m' => 9100,
                'duration_seconds' => 900,
                'coordinates' => [[10.96, -74.81], [10.962, -74.808], [10.964, -74.806], [10.966, -74.804]],
            ]),
        ]),
        new CiclorutaService(__DIR__.'/../Fixtures/ciclorutas-test.geojson')
    );

    $routes = $service->routesViaCiclorutas(
        ['lat' => 10.962, 'lng' => -74.808],
        ['lat' => 10.963, 'lng' => -74.764]
    );

    // La diferencia de infraestructura no justifica multiplicar por 9 el
    // recorrido: la ruta directa sigue siendo la recomendada.
    expect($routes[0]['distance_m'])->toBe(1000.0)
        ->and($routes[0]['cicloruta_coverage_pct'])->toBe(0);
});
