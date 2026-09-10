<?php

use App\Enums\RouteProfile;
use App\Services\Routing\RouteProfileRanker;
use App\Services\Routing\Safety\InfrastructureSafetyScoringService;

/**
 * Candidatas del motor común, tal y como las entrega el servicio tras
 * normalizar (campos de elevación ya nullable, métricas de infra reales).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function rankerRoute(array $overrides): array
{
    return array_merge([
        'distance_m' => 1000.0,
        'duration_seconds' => 100.0,
        'distance_km' => 1.0,
        'duration_min' => 2,
        'coordinates' => [[11.0, -74.78], [11.02, -74.78]],
        'steps' => [],
        'summary' => 'Directa',
        'driver' => 'osrm',
    ], $overrides);
}

$ranker = fn () => new RouteProfileRanker(new InfrastructureSafetyScoringService());

test('fastest elige la menor duración aunque sea más larga', function () use ($ranker) {
    $routes = [
        rankerRoute(['duration_seconds' => 60, 'distance_m' => 2000]),
        rankerRoute(['duration_seconds' => 120, 'distance_m' => 1000]),
    ];

    expect($ranker()->preferred(RouteProfile::Fastest, $routes)['distance_m'])->toBe(2000);
});

test('shortest elige la menor distancia aunque sea más lenta', function () use ($ranker) {
    $routes = [
        rankerRoute(['duration_seconds' => 60, 'distance_m' => 2000]),
        rankerRoute(['duration_seconds' => 120, 'distance_m' => 1000]),
    ];

    expect($ranker()->preferred(RouteProfile::Shortest, $routes)['distance_m'])->toBe(1000);
});

test('easiest prioriza las rutas con elevación real y elige el menor ascenso', function () use ($ranker) {
    $routes = [
        // Sin elevación real: debe quedar al final de los que NO traen dato.
        rankerRoute(['elevation_available' => false, 'ascent_m' => null, 'duration_seconds' => 10]),
        rankerRoute(['elevation_available' => true, 'ascent_m' => 45.0, 'duration_seconds' => 50]),
        rankerRoute(['elevation_available' => true, 'ascent_m' => 12.0, 'duration_seconds' => 80]),
    ];

    $preferred = $ranker()->preferred(RouteProfile::Easiest, $routes);

    expect($preferred['ascent_m'])->toBe(12.0);
});

test('easiest sin elevación real usa la duración como proxy honesto', function () use ($ranker) {
    $routes = [
        rankerRoute(['elevation_available' => false, 'ascent_m' => null, 'duration_seconds' => 80, 'distance_m' => 2000]),
        rankerRoute(['elevation_available' => false, 'ascent_m' => null, 'duration_seconds' => 50, 'distance_m' => 1000]),
    ];

    expect($ranker()->preferred(RouteProfile::Easiest, $routes)['duration_seconds'])->toBe(50);
});

test('scenic elige la mayor cobertura real de infraestructura, desempate por duración', function () use ($ranker) {
    $routes = [
        rankerRoute(['cicloruta_coverage_pct' => 20, 'duration_seconds' => 3000]),
        rankerRoute(['cicloruta_coverage_pct' => 90, 'duration_seconds' => 600]),
        rankerRoute(['cicloruta_coverage_pct' => 90, 'duration_seconds' => 400]),
    ];

    $preferred = $ranker()->preferred(RouteProfile::Scenic, $routes);

    expect($preferred['cicloruta_coverage_pct'])->toBe(90)
        ->and($preferred['duration_seconds'])->toBe(400);
});

test('safest en fase 1 ordena por infraestructura ciclista real, no por rapidez', function () use ($ranker) {
    $routes = [
        rankerRoute(['cicloruta_coverage_pct' => 0, 'duration_seconds' => 60]),
        rankerRoute(['cicloruta_coverage_pct' => 85, 'duration_seconds' => 900]),
    ];

    $preferred = $ranker()->preferred(RouteProfile::Safest, $routes);

    expect($preferred['cicloruta_coverage_pct'])->toBe(85);
});

test('preferred devuelve null sin candidatas', function () use ($ranker) {
    expect($ranker()->preferred(RouteProfile::Fastest, []))->toBeNull()
        ->and($ranker()->rank(RouteProfile::Fastest, []))->toBe([]);
});

test('rank devuelve las rutas intactas ante un perfil desconocido', function () use ($ranker) {
    $routes = [rankerRoute([]), rankerRoute(['distance_m' => 500])];

    expect($ranker()->rank('no-existe', $routes))->toBe($routes);
});