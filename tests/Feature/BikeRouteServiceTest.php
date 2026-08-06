<?php

use App\Exceptions\RouteCalculationException;
use App\Models\User;
use App\Services\BikeRouteService;
use App\Services\RouteProviders\GoogleDirectionsProvider;
use Illuminate\Support\Facades\Http;

$rutaGoogle = [
    'status' => 'OK',
    'routes' => [
        [
            'overview_polyline' => ['points' => 'k|`bAffngMcQvcAo}@n}@v|A_|B'],
            'legs' => [
                [
                    'distance' => ['value' => 8450],
                    'duration' => ['value' => 2100],
                ],
            ],
        ],
    ],
];

test('BikeRouteService calcula una ruta con Google Directions en modo bicicleta', function () use ($rutaGoogle) {
    Http::fake(['maps.googleapis.com/maps/api/directions/*' => Http::response($rutaGoogle, 200)]);

    $servicio = new BikeRouteService(new GoogleDirectionsProvider());
    $resultado = $servicio->calculate('10.9871,-74.7890', '10.9850,-74.7900');

    expect($resultado->polyline)->not->toBeEmpty()
        ->and($resultado->distance)->toBe(8450)
        ->and($resultado->duration)->toBe(2100)
        ->and($resultado->coordinates)->not->toBeEmpty()
        ->and($resultado->coordinates[0])->toHaveKeys(['lat', 'lng'])
        ->and($resultado->distanceInKilometers())->toBe(8.45)
        ->and($resultado->durationInMinutes())->toBe(35);
});

test('BikeRouteService lanza una excepción cuando Google no calcula la ruta', function () {
    Http::fake([
        'maps.googleapis.com/maps/api/directions/*' => Http::response([
            'status' => 'NOT_FOUND',
            'error_message' => 'No se encontró ruta',
        ], 200),
    ]);

    $servicio = new BikeRouteService(new GoogleDirectionsProvider());

    expect(fn () => $servicio->calculate('0,0', '1,1'))
        ->toThrow(RouteCalculationException::class);
});

test('el endpoint de cálculo de rutas devuelve la ruta calculada', function () use ($rutaGoogle) {
    Http::fake(['maps.googleapis.com/maps/api/directions/*' => Http::response($rutaGoogle, 200)]);

    $usuario = User::factory()->create();

    $this->actingAs($usuario)->postJson(route('rutas.calcular'), [
        'origen' => '10.9871,-74.7890',
        'destino' => '10.9850,-74.7900',
    ])->assertOk()
        ->assertJsonStructure(['polyline', 'distance', 'duration', 'coordinates'])
        ->assertJsonPath('distance', 8450)
        ->assertJsonPath('duration', 2100);
});

test('el endpoint de cálculo de rutas valida los campos requeridos', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)->postJson(route('rutas.calcular'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['origen', 'destino']);
});

test('el endpoint de cálculo de rutas exige autenticación', function () {
    $this->postJson(route('rutas.calcular'), [
        'origen' => '10.9871,-74.7890',
        'destino' => '10.9850,-74.7900',
    ])->assertUnauthorized();
});
