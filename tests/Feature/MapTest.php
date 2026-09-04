<?php

use App\Models\Barrio;
use App\Models\Bicicleta;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('los invitados no pueden acceder al mapa', function () {
    $this->get(route('mapa'))->assertRedirect(route('login'));
});

test('un usuario autenticado puede ver la página del mapa', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('mapa'))
        ->assertStatus(200)
        ->assertSee('¿A dónde quieres ir?')
        ->assertSee('Priorizar ciclorrutas')
        ->assertSee('CicleVibes');
});

test('la búsqueda requiere autenticación', function () {
    $this->getJson(route('maps.search'))->assertUnauthorized();
});

test('la búsqueda valida el parámetro requerido', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('maps.search'))
        ->assertStatus(422)
        ->assertJsonValidationErrors('q');
});

test('el endpoint de búsqueda devuelve resultados para una consulta válida', function () {
    $user = User::factory()->create();

    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            [
                'place_id' => 123,
                'name' => 'Parque Venezuela',
                'display_name' => 'Parque Venezuela, Barranquilla, Atlántico, Colombia',
                'lat' => 11.0014556,
                'lon' => -74.8241723,
                'addresstype' => 'park',
            ],
        ]),
    ]);

    $this->actingAs($user)
        ->getJson(route('maps.search', ['q' => 'Parque Venezuela, Barranquilla']))
        ->assertOk()
        ->assertJsonStructure(['results'])
        ->assertJsonCount(1, 'results');
});

test('el endpoint de ruta requiere autenticación', function () {
    $this->getJson(route('maps.route'))->assertUnauthorized();
});

test('el endpoint de ruta valida las coordenadas', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('maps.route'))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['origin_lat', 'origin_lng', 'dest_lat', 'dest_lng']);
});

test('el endpoint de ruta rechaza coordenadas fuera de rango', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('maps.route', [
            'origin_lat' => 95,
            'origin_lng' => -74,
            'dest_lat' => 10,
            'dest_lng' => -74,
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('origin_lat');
});

test('el endpoint de bicicletas requiere autenticación', function () {
    $this->getJson(route('maps.bicicletas'))->assertUnauthorized();
});

test('el endpoint de bicicletas devuelve la estructura esperada', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('maps.bicicletas'))
        ->assertOk()
        ->assertJsonStructure(['bicicletas']);
});

test('el endpoint de bicicletas incluye las bicicletas activas georreferenciadas', function () {
    $barrio = Barrio::create([
        'nombre' => 'Centro',
        'latitude' => 10.9639,
        'longitude' => -74.7964,
    ]);

    $bike = Bicicleta::factory()->create([
        'barrio_id' => $barrio->id,
    ]);

    $this->actingAs($bike->user)
        ->getJson(route('maps.bicicletas'))
        ->assertOk()
        ->assertJsonCount(1, 'bicicletas')
        ->assertJsonFragment(['marca' => $bike->marca, 'barrio' => 'Centro']);
});

test('el endpoint de ruta calcula una ruta mediante OSRM', function () {
    $user = User::factory()->create();

    Http::fake([
        'router.project-osrm.org/*' => Http::response([
            'code' => 'Ok',
            'routes' => [
                [
                    'distance' => 4513.6,
                    'duration' => 820.6,
                    'geometry' => 'o}qxF|bhvSnA{@',
                    'legs' => [[
                        'steps' => [
                            ['name' => 'Calle 72', 'distance' => 4000, 'duration' => 700, 'maneuver' => ['type' => 'depart', 'modifier' => 'straight']],
                            ['name' => 'Cicloruta', 'distance' => 513, 'duration' => 120, 'maneuver' => ['type' => 'turn', 'modifier' => 'right']],
                        ],
                    ]],
                ],
            ],
            'waypoints' => [],
        ]),
    ]);

    $this->actingAs($user)
        ->getJson(route('maps.route', [
            'origin_lat' => 11.006,
            'origin_lng' => -74.782,
            'dest_lat' => 10.985,
            'dest_lng' => -74.766,
        ]))
        ->assertOk()
        ->assertJsonStructure(['route' => ['distance_km', 'distance_m', 'duration_min', 'duration_seconds', 'coordinates', 'steps', 'profile', 'driver', 'label']])
        ->assertJsonFragment(['driver' => 'osrm', 'profile' => 'cycling', 'distance_km' => 4.51, 'duration_min' => 14])
        ->assertJsonFragment(['instruction' => 'Avanza hacia Calle 72']);
});

test('el endpoint de alternativas requiere autenticación', function () {
    $this->getJson(route('maps.alternatives'))->assertUnauthorized();
});

test('el endpoint de alternativas valida las coordenadas', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('maps.alternatives'))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['origin_lat', 'origin_lng', 'dest_lat', 'dest_lng']);
});

test('el endpoint de alternativas devuelve varias rutas', function () {
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
        ->assertJsonCount(2, 'routes')
        ->assertJsonFragment(['label' => 'Ruta recomendada'])
        ->assertJsonFragment(['label' => 'Ruta alternativa']);
});

test('el endpoint de recalculación requiere autenticación', function () {
    $this->getJson(route('maps.recalculate'))->assertUnauthorized();
});

test('el endpoint de recalculación devuelve una ruta', function () {
    $user = User::factory()->create();

    Http::fake([
        'router.project-osrm.org/*' => Http::response([
            'code' => 'Ok',
            'routes' => [
                ['distance' => 5000, 'duration' => 1100, 'geometry' => 'o}qxF|bhvSnA{@', 'legs' => [['steps' => []]]],
            ],
            'waypoints' => [],
        ]),
    ]);

    $this->actingAs($user)
        ->getJson(route('maps.recalculate', [
            'origin_lat' => 11.01, 'origin_lng' => -74.78,
            'dest_lat' => 10.985, 'dest_lng' => -74.766,
        ]))
        ->assertOk()
        ->assertJsonStructure(['route' => ['distance_km', 'coordinates', 'driver']]);
});

test('el endpoint de ciclorutas requiere autenticación', function () {
    $this->getJson(route('maps.cyclorutas'))->assertUnauthorized();
});

test('el endpoint de ciclorutas valida el bounding box', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('maps.cyclorutas'))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['min_lat', 'min_lng', 'max_lat', 'max_lng']);
});

test('el endpoint de ciclorutas devuelve infraestructura ciclista GeoJSON', function () {
    $user = User::factory()->create();

    Http::fake([
        'overpass-api.de/*' => Http::response([
            'type' => 'FeatureCollection',
            'features' => [],
            'elements' => [
                [
                    'type' => 'way',
                    'tags' => ['highway' => 'cycleway', 'name' => 'Cicloruta Test'],
                    'geometry' => [['lat' => 11.0, 'lon' => -74.78], ['lat' => 11.01, 'lon' => -74.77]],
                ],
            ],
        ]),
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('maps.cyclorutas', [
            'min_lat' => 10.9, 'min_lng' => -74.9,
            'max_lat' => 11.1, 'max_lng' => -74.6,
        ]))
        ->assertOk()
        ->assertJson(['type' => 'FeatureCollection'])
        ->assertJsonCount(1, 'features');

    $feature = $response->json('features.0');
    expect($feature['geometry']['type'])->toBe('LineString')
        ->and($feature['properties']['name'])->toBe('Cicloruta Test');
});
