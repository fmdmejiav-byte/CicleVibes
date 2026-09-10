<?php

use App\Models\User;
use App\Support\Polyline;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
});

$overpassEmpty = fn () => Http::response(['elements' => []]);

$overpassWithResidentialWay = function (Request $request) {
    parse_str($request->body(), $payload);
    $query = (string) ($payload['data'] ?? '');
    if (! preg_match('/around:\d+,\s*([^)]+)/', $query, $m)) {
        return Http::response(['elements' => []]);
    }

    $nums = array_map('floatval', explode(',', trim($m[1], "\t\n\r .")));
    $pairs = array_chunk($nums, 2);
    $geometry = [];
    foreach ($pairs as [$lat, $lng]) {
        $geometry[] = ['lat' => $lat + 0.0001, 'lon' => $lng + 0.0001];
    }

    return Http::response(['elements' => [
        ['type' => 'way', 'geometry' => $geometry, 'tags' => [
            'highway' => 'residential',
            'maxspeed' => '40',
            'lit' => 'yes',
            'surface' => 'asphalt',
        ]],
    ]]);
};

test('el endpoint de perfiles requiere autenticación', function () {
    $this->getJson(route('maps.profiles'))->assertUnauthorized();
});

test('el endpoint de perfiles valida las coordenadas', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('maps.profiles'))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['origin_lat', 'origin_lng', 'dest_lat', 'dest_lng']);
});

test('el endpoint de perfiles rechaza coordenadas fuera de rango', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('maps.profiles', [
            'origin_lat' => 95,
            'origin_lng' => -74,
            'dest_lat' => 10,
            'dest_lng' => -74,
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('origin_lat');
});

test('el endpoint de perfiles devuelve una ruta por perfil y la respuesta esperada', function () use ($overpassEmpty) {
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
        // Overpass responde correctamente pero sin datos mapeados en el
        // corredor: el Safety Score es null de forma determinista (fallback
        // honesto de cobertura real, nunca un valor inventado).
        'overpass-api.de/api/interpreter*' => $overpassEmpty(),
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('maps.profiles', [
            'origin_lat' => 11.006, 'origin_lng' => -74.782,
            'dest_lat' => 10.985, 'dest_lng' => -74.766,
        ]))
        ->assertOk()
        ->assertJsonStructure([
            'profiles' => [
                '*' => ['profile', 'label', 'emoji', 'description', 'route'],
            ],
            'profiles_requested',
            'query' => ['origin', 'destination'],
        ]);

    $profiles = $response->json('profiles');
    expect($profiles)->not->toBeEmpty()
        ->and(array_column($profiles, 'profile'))->toContain('fastest')
        ->and(array_column($profiles, 'profile'))->toContain('shortest');

    foreach ($profiles as $entry) {
        expect($entry['route'])->not->toBeNull()
            ->and($entry['route']['profile'])->toBe($entry['profile'])
            ->and($entry['route']['metadata']['elevation_available'])->toBeFalse()
            ->and($entry['route']['metadata']['safety_score'])->toBeNull()
            ->and($entry['route']['metadata']['safety_explanation'])->toContain('insuficiente')
            ->and($entry['route']['metadata']['safety_signals'])->toBeArray()
            ->and($entry['route']['metadata']['safety_sources'])->toHaveKey('road_type');
    }
});

test('el endpoint de perfiles respeta la lista solicitada y el priorizado de ciclorrutas', function () {
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
        ->getJson(route('maps.profiles', [
            'origin_lat' => 11.006, 'origin_lng' => -74.782,
            'dest_lat' => 10.985, 'dest_lng' => -74.766,
            'profiles' => 'scenic,safest',
            'priorize_ciclorutas' => 1,
        ]))
        ->assertOk();

    expect($response->json('profiles_requested'))->toBe(['scenic', 'safest'])
        ->and($response->json('priorize_ciclorutas'))->toBe(true)
        ->and(array_column($response->json('profiles'), 'profile'))->toBe(['scenic', 'safest']);
});

test('el endpoint de perfiles responde 422 cuando ninguna ruta es viable', function () {
    $user = User::factory()->create();

    Http::fake([
        'router.project-osrm.org/*' => Http::response(
            ['message' => 'Something went wrong'],
            500
        ),
    ]);

    $this->actingAs($user)
        ->getJson(route('maps.profiles', [
            'origin_lat' => 11.006, 'origin_lng' => -74.782,
            'dest_lat' => 10.985, 'dest_lng' => -74.766,
        ]))
        ->assertStatus(422);
});

test('incluye un Safety Score real (0-100) con señales cuando los datos lo respaldan', function () use ($overpassWithResidentialWay) {
    $user = User::factory()->create();

    // Ruta real de ~4 km en Medellín (coordenadas y geometría coherentes).
    $route = [
        ['lat' => 6.262, 'lng' => -75.613],
        ['lat' => 6.251, 'lng' => -75.611],
        ['lat' => 6.238, 'lng' => -75.606],
        ['lat' => 6.225, 'lng' => -75.602],
        ['lat' => 6.197, 'lng' => -75.598],
    ];
    $geometry = Polyline::encode($route);

    Http::fake([
        'router.project-osrm.org/*' => Http::response([
            'code' => 'Ok',
            'routes' => [
                ['distance' => 4200, 'duration' => 1100, 'geometry' => $geometry, 'legs' => [['steps' => []]]],
            ],
            'waypoints' => [],
        ]),
        'overpass-api.de/api/interpreter*' => $overpassWithResidentialWay,
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('maps.profiles', [
            'origin_lat' => 6.262, 'origin_lng' => -75.613,
            'dest_lat' => 6.197, 'dest_lng' => -75.598,
        ]))
        ->assertOk();

    $profiles = $response->json('profiles');
    expect($profiles)->not->toBeEmpty();

    foreach ($profiles as $entry) {
        $metadata = $entry['route']['metadata'];

        expect($metadata['safety_score'])->toBeGreaterThan(0)
            ->and($metadata['safety_score'])->toBeLessThanOrEqual(100)
            ->and($metadata['safety_confidence'])->toBeGreaterThan(0.0)
            ->and($metadata['safety_explanation'])->toBeString()
            ->and($metadata['safety_signals'])->toBeArray()
            ->and($metadata['safety_sources'])->toHaveKey('road_type');

        $types = array_column($metadata['safety_signals'], 'type');
        expect($types)->toContain('road_type')
            ->and($types)->toContain('speed');
    }
});

test('el endpoint de recalculación conserva el perfil seleccionado', function () {
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
            'profile' => 'scenic',
        ]))
        ->assertOk()
        ->assertJsonStructure(['route' => ['distance_km', 'coordinates', 'driver']])
        ->assertJsonFragment(['profile' => 'scenic']);
});