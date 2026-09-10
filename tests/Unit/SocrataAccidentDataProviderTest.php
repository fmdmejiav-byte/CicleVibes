<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Services\Routing\Safety\SocrataAccidentDataProvider;

/**
 * Proveedor de siniestralidad vial (dataset público ANSV vía Socrata).
 * El fake replica el filtrado por $where que hace la API real y solo debe
 * devolver registros dentro del bbox consultado.
 */

beforeEach(function () {
    Cache::flush();
    config()->set('safety.accidents.enabled', true);
});

$barranquillaRoute = fn () => [
    [11.0010, -74.7820],
    [10.9960, -74.7750],
    [10.9900, -74.7670],
    [10.9850, -74.7600],
];

$sodaFake = function (array $records): callable {
    return function (Request $request) use ($records) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $where = (string) ($query['$where'] ?? '');

        if (! preg_match('/latitud between (-?[\d.]+) and (-?[\d.]+) AND longitud between (-?[\d.]+) and (-?[\d.]+)/', $where, $m)) {
            return Http::response([]);
        }

        [$minLat, $maxLat, $minLng, $maxLng] = array_map('floatval', array_slice($m, 1));

        return Http::response(array_values(array_filter($records, function (array $row) use ($minLat, $maxLat, $minLng, $maxLng) {
            return $row['latitud'] >= $minLat && $row['latitud'] <= $maxLat
                && $row['longitud'] >= $minLng && $row['longitud'] <= $maxLng;
        })));
    };
};

test('emite señal de siniestralidad con los sectores críticos reales del corredor', function () use ($barranquillaRoute, $sodaFake) {
    Http::fake([
        'datos.gov.co/resource/rs3u-8r4q.json*' => $sodaFake([
            ['latitud' => 11.000000, 'longitud' => -74.781000, 'fallecidos' => 2, 'nombre' => 'Sector A'],
            ['latitud' => 10.990500, 'longitud' => -74.768000, 'fallecidos' => 1, 'nombre' => 'Sector B'],
        ]),
    ]);

    $provider = new SocrataAccidentDataProvider();
    $signals = $provider->signals($barranquillaRoute());

    expect($signals)->toHaveCount(1)
        ->and($signals[0]->type)->toBe('accidents')
        ->and($signals[0]->value)->toBeGreaterThan(0)
        ->and($signals[0]->value)->toBeLessThan(100)
        ->and($signals[0]->metadata['count'])->toBe(2)
        ->and($signals[0]->metadata['fatalities_total'])->toBe(3)
        ->and($signals[0]->metadata['dataset'])->toBe('rs3u-8r4q')
        ->and($signals[0]->metadata['per_km'])->toBeGreaterThan(0);
});

test('sin registros en el corredor la señal se omite (no concluye seguridad)', function () use ($barranquillaRoute, $sodaFake) {
    Http::fake([
        'datos.gov.co/resource/rs3u-8r4q.json*' => $sodaFake([]),
    ]);

    $provider = new SocrataAccidentDataProvider();
    $signals = $provider->signals($barranquillaRoute());

    expect($signals)->toBeEmpty();
});

test('deshabilitado por configuración no consulta la red', function () use ($barranquillaRoute) {
    config()->set('safety.accidents.enabled', false);
    Http::fake();

    $provider = new SocrataAccidentDataProvider();

    expect($provider->signals($barranquillaRoute()))->toBeEmpty();
});

test('satura la penalización a cero cuando la densidad es muy alta', function () use ($barranquillaRoute, $sodaFake) {
    $records = [];
    for ($i = 0; $i < 40; $i++) {
        $records[] = ['latitud' => 11.0010 - $i * 0.0004, 'longitud' => -74.7820 + $i * 0.0005, 'fallecidos' => 1];
    }

    Http::fake([
        'datos.gov.co/resource/rs3u-8r4q.json*' => $sodaFake($records),
    ]);

    $provider = new SocrataAccidentDataProvider();
    $signals = $provider->signals($barranquillaRoute());

    expect($signals)->toHaveCount(1)
        ->and($signals[0]->type)->toBe('accidents')
        ->and($signals[0]->value)->toBe(0.0);
});

test('documenta la fuente pública para auditoría', function () {
    config()->set('safety.accidents.enabled', true);

    $source = (new SocrataAccidentDataProvider())->sourceDescription();

    expect($source)->toHaveKeys(['name', 'url', 'license', 'coverage', 'update_frequency', 'api_key_required'])
        ->and($source['license'])->toContain('CC BY-SA')
        ->and($source['url'])->toContain('rs3u-8r4q')
        ->and($source['api_key_required'])->toBeFalse();
});
