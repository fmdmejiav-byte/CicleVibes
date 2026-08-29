<?php

use App\Services\Maps\CiclorutaService;

function ciclorutaService(): CiclorutaService
{
    return new CiclorutaService(__DIR__.'/../Fixtures/ciclorutas-test.geojson');
}

/**
 * Escribe un GeoJSON temporal de prueba.
 *
 * @param  array<int, array<string, mixed>>  $features
 */
function ciclorutaTempFile(array $features): string
{
    $dir = sys_get_temp_dir().'/ciclorutas-test-'.bin2hex(random_bytes(6));
    mkdir($dir, 0755, true);
    $path = $dir.'/red.geojson';
    file_put_contents($path, json_encode([
        'type' => 'FeatureCollection',
        '_meta' => ['title' => 'red temporal de prueba', 'official_total_km' => 0],
        'features' => $features,
    ]));

    return $path;
}

/**
 * @return array<string, mixed>
 */
function ciclorutaLineFeature(string $name, array $coords): array
{
    return [
        'type' => 'Feature',
        'properties' => ['name' => $name, 'type' => 'ciclobanda'],
        'geometry' => ['type' => 'LineString', 'coordinates' => $coords],
    ];
}

test('nearest devuelve la ciclorruta más cercana con proyección a la geometría', function () {
    $service = ciclorutaService();

    // Punto ~90 m off the "Carrera 47" del fixture.
    $nearest = $service->nearest(10.9612, -74.808);

    expect($nearest)->not->toBeNull()
        ->and($nearest['name'])->toBe('Carrera 47')
        ->and($nearest['type'])->toBe('ciclobanda')
        ->and($nearest['type_label'])->toBe('Ciclobanda')
        ->and($nearest['distance_m'])->toBeGreaterThan(0)
        ->and($nearest['distance_m'])->toBeLessThan(250)
        ->and($nearest['segment']['geometry']['type'])->toBe('LineString');
});

test('nearest proyecta sobre la línea, no usa el centro del segmento', function () {
    $service = ciclorutaService();

    // Punto justo sobre un vértice de la red.
    $nearest = $service->nearest(10.96, -74.81);

    expect($nearest['distance_m'])->toBeLessThan(1)
        ->and($nearest['nearest_lat'])->toEqualWithDelta(10.96, 0.0001)
        ->and($nearest['nearest_lng'])->toEqualWithDelta(-74.81, 0.0001);
});

test('pathBetween encuentra un recorrido por la red conectada', function () {
    $service = ciclorutaService();

    $path = $service->pathBetween(10.962, -74.808, 10.9975, -74.772);

    expect($path)->not->toBeNull()
        ->and($path['distance_m'])->toBeGreaterThan(5000)
        ->and(count($path['coordinates']))->toBeGreaterThanOrEqual(4)
        ->and($path['used'])->toHaveCount(2);

    $names = array_column($path['used'], 'name');
    expect($names)->toContain('Carrera 47')
        ->and($names)->toContain('Calle 44');
});

test('pathBetween devuelve null si la red no conecta los accesos', function () {
    $service = ciclorutaService();

    // Origen sobre la red principal, destino sobre la "Ciclovía Aislada".
    $path = $service->pathBetween(10.962, -74.808, 10.963, -74.764);

    expect($path)->toBeNull();
});

test('la red GeoJSON se carga y conserva los tipos', function () {
    $service = ciclorutaService();

    $network = $service->network();

    expect($network['type'])->toBe('FeatureCollection')
        ->and($network['features'])->toHaveCount(3)
        ->and($service->typeLabel('ciclobanda'))->toBe('Ciclobanda')
        ->and($service->types())->toHaveCount(4);
});

test('los nodos separados 20-50 m se registran como sospechosos pero no se conectan', function () {
    $path = ciclorutaTempFile([
        ciclorutaLineFeature('Ruta A', [[-74.8, 10.96], [-74.7994, 10.96]]),
        ciclorutaLineFeature('Ruta B', [[-74.799, 10.96], [-74.7982, 10.96]]),
    ]);

    $service = new CiclorutaService($path);

    // La separación entre A (-74.7994) y B (-74.799) es de ~43 m.
    $gaps = $service->suspiciousGaps();
    expect($gaps)->toHaveCount(1)
        ->and($gaps[0]['distance_m'])->toBeGreaterThan(20)
        ->and($gaps[0]['distance_m'])->toBeLessThanOrEqual(50);

    // No se conectaron: dos componentes y sin trayecto entre rutas.
    $components = $service->connectedComponents(true);
    expect($components['components'])->toHaveCount(2)
        ->and($components['by_node'])->toHaveCount(4)
        ->and($service->diagnostics()['auto_joined_pairs'])->toBe(0)
        ->and($service->pathBetween(10.96, -74.79945, 10.96, -74.7986))->toBeNull();
});

test('los nodos separados por menos de 20 m se conectan automáticamente', function () {
    $path = ciclorutaTempFile([
        ciclorutaLineFeature('Ruta C', [[-74.8, 10.961], [-74.799, 10.961]]),
        ciclorutaLineFeature('Ruta D', [[-74.7989, 10.961], [-74.7979, 10.961]]),
    ]);

    $service = new CiclorutaService($path);

    // Separación de ~11 m entre los extremos: se une y la red queda conectada.
    expect($service->diagnostics()['auto_joined_pairs'])->toBe(1)
        ->and($service->connectedComponents(true)['components'])->toHaveCount(1)
        ->and($service->suspiciousGaps())->toBeEmpty()
        ->and($service->pathBetween(10.961, -74.7995, 10.961, -74.7983))->not->toBeNull();
});

test('auditReport resume la red con componentes, tipos y segmentos cortos', function () {
    $service = ciclorutaService();

    $report = $service->auditReport();

    expect($report['summary']['features_count'])->toBe(3)
        ->and($report['summary']['total_km'])->toBeGreaterThan(7)
        ->and($report['summary']['components_after_join'])->toBe(2)
        ->and($report['components']['after_join'])->toHaveCount(2)
        ->and($report['corridors'])->toHaveCount(3)
        ->and(collect($report['isolated_segments'])->firstWhere('name', 'Ciclovía Aislada'))->not->toBeNull();
});

test('safetyWeight pondera más la infraestructura protegida', function () {
    $service = ciclorutaService();

    expect($service->safetyWeight('ciclorruta_calzada'))->toBe(1.0)
        ->and($service->safetyWeight('ciclorruta_anden'))->toBe(0.9)
        ->and($service->safetyWeight('ciclobanda'))->toBe(0.7)
        ->and($service->safetyWeight('carril_ciclo_preferente'))->toBe(0.5)
        ->and($service->safetyWeight('calle_sin_ciclovia'))->toBe(0.0);
});

test('routeInfraStats mide la coincidencia con la red sobre la geometría de la ruta', function () {
    $service = ciclorutaService();

    // Geometría sobre la "Carrera 47" del fixture: 100% coincidente.
    $onNetwork = [
        [10.96, -74.81],
        [10.961, -74.809],
        [10.962, -74.808],
        [10.963, -74.807],
    ];
    $stats = $service->routeInfraStats($onNetwork);

    expect($stats['coverage_pct'])->toBe(100)
        ->and($stats['matched_m'])->toBeGreaterThan(0)
        ->and($stats['unmatched_m'])->toEqual(0.0)
        ->and($stats['safety_index'])->toBeGreaterThan(0)
        ->and($stats['by_type_m'])->toHaveKey('ciclobanda');

    // Geometría lejos de la red: ninguna coincidencia.
    $farAway = [
        [11.03, -74.75],
        [11.035, -74.75],
    ];
    $farStats = $service->routeInfraStats($farAway);

    expect($farStats['coverage_pct'])->toBe(0)
        ->and($farStats['matched_m'])->toBe(0.0)
        ->and($farStats['safety_index'])->toBe(0.0);
});

test('routeInfraStats usa la longitud oficial cuando la geometría no refleja la ruta', function () {
    $service = ciclorutaService();

    $geometry = [
        [10.96, -74.81],
        [10.97, -74.8],
    ];

    // Geometría real (coincide con "Carrera 47", ~1558 m) pero con legs
    // artificiales que la alargan fuera de la red: la métrica debe usar
    // la longitud oficial reportada como denominador.
    $stats = $service->routeInfraStats($geometry, 2000);

    expect($stats['total_m'])->toBe(2000.0)
        ->and($stats['coverage_pct'])->toBeGreaterThan(0)
        ->and($stats['coverage_pct'])->toBeLessThanOrEqual(100);
});

test('nearestNodeAt devuelve el nodo de la red más cercano', function () {
    $service = ciclorutaService();

    // Sobre "Carrera 47".
    expect($service->nearestNodeAt(10.961, -74.809))->toBeInt();
});

test('exitToward busca un punto de salida en el componente que equilibra red y hueco', function () {
    $service = ciclorutaService();

    $exit = $service->exitToward(10.962, -74.808, 11.0, -74.77, 0.5);

    expect($exit)->not->toBeNull()
        ->and($exit['lat'])->toBeFloat()
        ->and($exit['lng'])->toBeFloat()
        ->and((float) $exit['network_distance_m'])->toBeGreaterThan(0)
        ->and((float) $exit['gap_m'])->toBeGreaterThanOrEqual(0)
        ->and((float) $exit['network_distance_m'])->toBeGreaterThan((float) $exit['gap_m']);
});
