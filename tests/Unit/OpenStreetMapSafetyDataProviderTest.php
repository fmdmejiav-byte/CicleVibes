<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Services\Routing\Safety\OpenStreetMapSafetyDataProvider;

/**
 * Proveedor OpenStreetMap (Overpass): señales reales desde elementos OSM
 * simulados. El fake DEBE replicar lo que devuelve la API real: ways con
 * geometría y tags, y nodes de cruce/calmado.
 */

beforeEach(function () {
    Cache::flush();
});

/**
 * Construye un fake de Overpass que coloca una "via" residencial EXACTAMENTE
 * sobre los puntos muestreados que el proveedor envía en la consulta
 * `around(...)`. Así las señales son deterministas (cobertura ~1) para
 * cualquier geometría de ruta de prueba.
 */
$overpassWithWayOnRoute = function (array $tags): callable {
    return function (Request $request) use ($tags) {
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
            ['type' => 'way', 'geometry' => $geometry, 'tags' => $tags],
        ]]);
    };
};

$barranquillaRoute = fn () => [
    [11.0010, -74.7820],
    [10.9960, -74.7750],
    [10.9900, -74.7670],
    [10.9850, -74.7600],
];

test('lee etiquetas reales de highway, maxspeed, lit y surface del corredor', function () use ($barranquillaRoute, $overpassWithWayOnRoute) {
    Http::fake([
        'overpass-api.de/api/interpreter' => $overpassWithWayOnRoute([
            'highway' => 'residential',
            'maxspeed' => '40',
            'lit' => 'yes',
            'surface' => 'asphalt',
        ]),
    ]);

    $provider = new OpenStreetMapSafetyDataProvider();
    $signals = $provider->signals($barranquillaRoute());

    $byType = [];
    foreach ($signals as $signal) {
        $byType[$signal->type] = $signal;
    }

    expect($byType)->toHaveKey('road_type')
        ->and($byType['road_type']->value)->toBe(80.0)
        ->and($byType['road_type']->coverage)->toBeGreaterThan(0.9)
        ->and($byType['speed']->value)->toBe(70.0)
        ->and($byType['lighting']->value)->toBe(100.0)
        ->and($byType['surface']->value)->toBe(100.0);
});

test('no inventa señales cuando la vía no tiene highway (dato real ausente)', function () use ($barranquillaRoute, $overpassWithWayOnRoute) {
    Http::fake([
        'overpass-api.de/api/interpreter' => $overpassWithWayOnRoute([
            'name' => 'Calle sin etiquetar',
        ]),
    ]);

    $provider = new OpenStreetMapSafetyDataProvider();
    $signals = $provider->signals($barranquillaRoute());

    expect($signals)->toBeEmpty();
});

test('responde señales de cruce y calmado cuando Overpass las documenta', function () use ($barranquillaRoute) {
    Http::fake([
        'overpass-api.de/api/interpreter' => function (Request $request) {
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

            $mid = $pairs[(int) floor(count($pairs) / 2)];

            return Http::response(['elements' => [
                ['type' => 'way', 'geometry' => $geometry, 'tags' => [
                    'highway' => 'residential',
                    'maxspeed' => '40',
                ]],
                ['type' => 'node', 'lat' => $mid[0], 'lon' => $mid[1], 'tags' => ['highway' => 'crossing']],
                ['type' => 'node', 'lat' => $mid[0], 'lon' => $mid[1], 'tags' => ['traffic_calming' => 'hump']],
            ]]);
        },
    ]);

    $provider = new OpenStreetMapSafetyDataProvider();
    $signals = $provider->signals($barranquillaRoute());

    $byType = [];
    foreach ($signals as $signal) {
        $byType[$signal->type] = $signal;
    }

    expect($byType)->toHaveKey('road_type')
        ->and($byType)->toHaveKey('crossings')
        ->and($byType['crossings']->metadata['count'])->toBeGreaterThan(0)
        ->and($byType)->toHaveKey('traffic_calming')
        ->and($byType['traffic_calming']->metadata['count'])->toBeGreaterThan(0);
});

test('ante una caída de Overpass devuelve un set vacío sin romper', function () use ($barranquillaRoute) {
    Http::fake([
        'overpass-api.de/api/interpreter' => Http::response(['remark' => 'rate_limited'], 429),
    ]);

    $provider = new OpenStreetMapSafetyDataProvider();
    $signals = $provider->signals($barranquillaRoute());

    expect($signals)->toBeEmpty();
});

test('no consulta la red cuando el proveedor está deshabilitado', function () use ($barranquillaRoute) {
    config()->set('safety.osm.enabled', false);

    Http::fake();

    $provider = new OpenStreetMapSafetyDataProvider();

    expect($provider->signals($barranquillaRoute()))->toBeEmpty();
});

test('documenta la fuente para auditoría', function () {
    $source = (new OpenStreetMapSafetyDataProvider())->sourceDescription();

    expect($source)->toHaveKeys(['name', 'url', 'license', 'coverage', 'api_key_required'])
        ->and($source['license'])->toContain('ODbL')
        ->and($source['api_key_required'])->toBeFalse();
});