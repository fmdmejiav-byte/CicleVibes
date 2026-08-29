<?php

use App\Models\User;
use App\Services\Maps\CiclorutaService;
use Illuminate\Support\Facades\Http;

/** Ruta al fixture de red de ciclorrutas para tests deterministas. */
function ciclorutaFixture(): string
{
    return __DIR__.'/../Fixtures/ciclorutas-test.geojson';
}

test('el endpoint de la red de ciclorrutas requiere autenticación', function () {
    $this->getJson(route('maps.ciclorutas'))->assertUnauthorized();
});

test('el endpoint de la red de ciclorrutas devuelve un FeatureCollection con tipos', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->getJson(route('maps.ciclorutas'))
        ->assertOk()
        ->assertJson(['type' => 'FeatureCollection'])
        ->assertJsonPath('_meta.official_total_km', 83.10)
        ->assertJsonStructure(['features' => [['type', 'properties', 'geometry']]]);

    $types = [];
    foreach ($response->json('features') as $feature) {
        $types[] = $feature['properties']['type'];
    }
    expect(array_unique($types))->toContain('ciclobanda');
});

test('el endpoint de ciclorruta más cercana valida las coordenadas', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('maps.ciclorutas.nearest'))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['lat', 'lng']);
});

test('el endpoint de ciclorruta más cercana devuelve el segmento más próximo', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('maps.ciclorutas.nearest', ['lat' => 10.995, 'lng' => -74.805]))
        ->assertOk()
        ->assertJsonStructure(['nearest' => [
            'name', 'type', 'type_label', 'distance_m', 'nearest_lat', 'nearest_lng', 'segment',
        ]]);
});

test('alternatives con priorizar ciclorrutas antepone la ruta por la red y mantiene fallback', function () {
    $user = User::factory()->create();

    app()->instance(
        CiclorutaService::class,
        new CiclorutaService(ciclorutaFixture())
    );

    Http::fake(function ($request) {
        $uri = $request->url();

        // La llamada directa va de origen a destino; las llamadas de acceso
        // son tramos cortos hasta la red de ciclorrutas.
        $direct = str_contains($uri, '-74.808,10.962') && str_contains($uri, '-74.772,10.9975');
        $distance = $direct ? 8600 : 500;
        $duration = $direct ? 1860 : 100;

        return Http::response([
            'code' => 'Ok',
            'routes' => [
                ['distance' => $distance, 'duration' => $duration, 'geometry' => 'o}qxF|bhvSnA{@', 'legs' => [['steps' => []]]],
            ],
            'waypoints' => [],
        ]);
    });

    $response = $this->actingAs($user)
        ->getJson(route('maps.alternatives', [
            'origin_lat' => 10.962,
            'origin_lng' => -74.808,
            'dest_lat' => 10.9975,
            'dest_lng' => -74.772,
            'priorize_ciclorutas' => 1,
        ]))
        ->assertOk();

    $routes = $response->json('routes');
    expect($routes)->not->toBeEmpty();

    // La primera alternativa prioriza la red de ciclorrutas.
    expect($routes[0]['via_ciclorutas'])->toBeTrue()
        ->and($routes[0]['driver'])->toBe('ciclorutas')
        ->and($routes[0]['label'])->toBe('🚲 Ruta por ciclorrutas')
        ->and($routes[0]['cicloruta_coverage_pct'])->toBeGreaterThan(0)
        ->and($routes[0]['ciclorutas_used'])->not->toBeEmpty()
        ->and($routes[0]['distance_km'])->toBeGreaterThan(0)
        ->and(count($routes[0]['coordinates']))->toBeGreaterThanOrEqual(3);

    // La ruta OSRM queda como alternativa posterior.
    expect($response->json('cicloruta_connection'))->toBeTrue()
        ->and($routes[1]['label'])->toBe('🛣️ Ruta alternativa');
});

test('alternatives con priorizar muestran fallback honesto si no hay conexión ciclista completa', function () {
    $user = User::factory()->create();

    app()->instance(
        CiclorutaService::class,
        new CiclorutaService(ciclorutaFixture())
    );

    Http::fake([
        'router.project-osrm.org/*' => Http::response([
            'code' => 'Ok',
            'routes' => [
                ['distance' => 8600, 'duration' => 1860, 'geometry' => 'o}qxF|bhvSnA{@', 'legs' => [['steps' => []]]],
            ],
            'waypoints' => [],
        ]),
    ]);

    // El destino está sobre la "Ciclovía Aislada", disconectada de la red
    // principal: no puede existir una ruta que la recorra por completo.
    $response = $this->actingAs($user)
        ->getJson(route('maps.alternatives', [
            'origin_lat' => 10.962,
            'origin_lng' => -74.808,
            'dest_lat' => 10.963,
            'dest_lng' => -74.764,
            'priorize_ciclorutas' => 1,
        ]))
        ->assertOk();

    expect($response->json('cicloruta_connection'))->toBeFalse()
        ->and($response->json('routes.0.via_ciclorutas') ?? false)->toBeFalse()
        ->and($response->json('routes.0.label'))->toBe('🛣️ Ruta alternativa');
});

test('alternatives sin priorizar conserva el comportamiento actual de OSRM', function () {
    $user = User::factory()->create();

    Http::fake([
        'router.project-osrm.org/*' => Http::response([
            'code' => 'Ok',
            'routes' => [
                ['distance' => 8600, 'duration' => 1860, 'geometry' => 'o}qxF|bhvSnA{@', 'legs' => [['steps' => []]]],
            ],
            'waypoints' => [],
        ]),
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('maps.alternatives', [
            'origin_lat' => 10.962,
            'origin_lng' => -74.808,
            'dest_lat' => 10.9975,
            'dest_lng' => -74.772,
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'routes');

    $route = $response->json('routes.0');
    expect($route['driver'])->toBe('osrm')
        ->and($route['via_ciclorutas'] ?? false)->toBeFalse()
        ->and($route['label'])->toBe('Ruta recomendada');
});

test('rules kept: alternatives still returns several routes via OSRM', function () {
    $user = User::factory()->create();

    Http::fake([
        'router.project-osrm.org/*' => Http::response([
            'code' => 'Ok',
            'routes' => [
                ['distance' => 8600, 'duration' => 1860, 'geometry' => 'o}qxF|bhvSnA{@', 'legs' => [['steps' => []]]],
                ['distance' => 10800, 'duration' => 2100, 'geometry' => 'o}qxF|bhvSnA{@', 'legs' => [['steps' => []]]],
            ],
            'waypoints' => [],
        ]),
    ]);

    $this->actingAs($user)
        ->getJson(route('maps.alternatives', [
            'origin_lat' => 11.006, 'origin_lng' => -74.782,
            'dest_lat' => 10.985, 'dest_lng' => -74.766,
        ]))
        ->assertOk()
        ->assertJsonCount(2, 'routes');
});
