<?php

use App\Contracts\AccidentDataProvider;
use App\Contracts\SafetyDataProvider;
use App\Safety\SafetySignal;
use App\Services\Routing\Safety\SafetyScoreService;

/**
 * Safety Score: servicio principal con proveedores simulados (nunca red).
 * Verifica las reglas de honestidad: no se publica un score si los datos
 * reales disponibles no lo respaldan (factores mínimos o confianza).
 */

$route = fn () => ['coordinates' => [
    [11.001, -74.780],
    [10.990, -74.770],
    [10.980, -74.760],
]];

$osmWith = fn (array $signals): SafetyDataProvider => new class($signals) implements SafetyDataProvider
{
    public function __construct(public array $signals) {}

    public function name(): string
    {
        return 'OpenStreetMap (fake)';
    }

    public function signals(array $coordinates): array
    {
        return $this->signals;
    }

    public function sourceDescription(): array
    {
        return ['name' => 'Fake'];
    }
};

$accidentsWith = fn (array $signals): AccidentDataProvider => new class($signals) implements AccidentDataProvider
{
    public function __construct(public array $signals) {}

    public function name(): string
    {
        return 'Siniestralidad (fake)';
    }

    public function signals(array $coordinates): array
    {
        return $this->signals;
    }

    public function sourceDescription(): array
    {
        return ['name' => 'Fake'];
    }
};

test('no publica score cuando hay menos factores reales que el mínimo', function () use ($route, $osmWith) {
    $service = new SafetyScoreService($osmWith([
        new SafetySignal('road_type', 80, 1.0, 'OpenStreetMap (fake)', 1.0),
    ]));

    $assessment = $service->assessment($route());

    expect($assessment['score'])->toBeNull()
        ->and($assessment['explanation'])->toContain('Información de seguridad insuficiente')
        ->and($assessment['confidence'])->toBeGreaterThan(0.0)
        ->and($assessment['unit'])->toBe('0-100')
        ->and($assessment['signals'])->toBeArray();
});

test('no publica score cuando la geometría de la ruta no es válida', function () {
    $service = new SafetyScoreService(
        new class implements SafetyDataProvider
        {
            public function name(): string
            {
                return 'Fake';
            }

            public function signals(array $coordinates): array
            {
                return [new SafetySignal('road_type', 80, 1.0, 'Fake', 1.0)];
            }

            public function sourceDescription(): array
            {
                return [];
            }
        }
    );

    $assessment = $service->assessment(['coordinates' => []]);

    expect($assessment['score'])->toBeNull()
        ->and($assessment['explanation'])->toContain('no tiene geometría');
});

test('calcula el score ponderado real con dos factores suficientes', function () use ($route, $osmWith) {
    $service = new SafetyScoreService($osmWith([
        new SafetySignal('road_type', 20, 1.0, 'OpenStreetMap (fake)', 1.0),
        new SafetySignal('speed', 40, 1.0, 'OpenStreetMap (fake)', 1.0),
    ]));

    $assessment = $service->assessment($route());

    // Peso road_type 0.22 + speed 0.18 → (0.22*20 + 0.18*40) / 0.40 = 29.
    expect($assessment['score'])->toBe(29.0)
        ->and(round($assessment['confidence'], 3))->toBe(round(0.40 / 0.86, 3))
        ->and($assessment['explanation'])->toContain('Safety Score estimado: 29/100')
        ->and($assessment['explanation'])->toContain('Jerarquía de vía');
});

test('usa el índice real de infraestructura local como factor', function () use ($route, $osmWith) {
    $route = $route();
    $route['cicloruta_safety_index'] = 0.9;
    $route['cicloruta_coverage_pct'] = 100;

    $service = new SafetyScoreService($osmWith([
        new SafetySignal('road_type', 20, 1.0, 'OpenStreetMap (fake)', 1.0),
    ]));

    $assessment = $service->assessment($route);

    // infra (0.9*100=90, peso 0.28) + road_type (20, peso 0.22)
    // → (0.28*90 + 0.22*20) / 0.50 = 59.2 → 59.
    expect($assessment['score'])->toBe(59.0)
        ->and(array_column($assessment['signals'], 'type'))->toContain('infrastructure');
});

test('integra la siniestralidad oficial cuando está habilitada', function () use ($route, $osmWith, $accidentsWith) {
    config()->set('safety.accidents.enabled', true);

    $service = new SafetyScoreService(
        $osmWith([
            new SafetySignal('road_type', 20, 1.0, 'OpenStreetMap (fake)', 1.0),
        ]),
        $accidentsWith([
            new SafetySignal('accidents', 60, 1.0, 'Siniestralidad (fake)', 1.0, null, ['count' => 4]),
        ])
    );

    $assessment = $service->assessment($route());

    // road_type 0.22 + accidents 0.14 → (0.22*20 + 0.14*60) / 0.36 ≈ 35.6 → 36.
    expect($assessment['score'])->toBe(36.0)
        ->and($assessment['explanation'])->toContain('Siniestralidad')
        ->and($assessment['sources']['accidents']['available'])->toBeTrue();
});

test('no publica score cuando la confianza no alcanza el mínimo', function () use ($route, $osmWith) {
    config()->set('safety.min_confidence', 0.25);

    $service = new SafetyScoreService($osmWith([
        new SafetySignal('road_type', 80, 0.05, 'OpenStreetMap (fake)', 0.05),
        new SafetySignal('speed', 60, 0.05, 'OpenStreetMap (fake)', 0.05),
    ]));

    $assessment = $service->assessment($route());

    expect($assessment['score'])->toBeNull()
        ->and($assessment['explanation'])->toContain('confianza');
});

test('score() replica el assessment', function () use ($route, $osmWith) {
    $service = new SafetyScoreService($osmWith([
        new SafetySignal('road_type', 20, 1.0, 'OpenStreetMap (fake)', 1.0),
        new SafetySignal('speed', 40, 1.0, 'OpenStreetMap (fake)', 1.0),
    ]));

    expect($service->score($route()))->toBe(29.0);
});

test('por defecto la siniestralidad deshabilitada no diluye la confianza', function () use ($route, $osmWith, $accidentsWith) {
    config()->set('safety.accidents.enabled', false);

    $service = new SafetyScoreService(
        $osmWith([
            new SafetySignal('road_type', 20, 1.0, 'OpenStreetMap (fake)', 1.0),
        ]),
        $accidentsWith([
            new SafetySignal('accidents', 5, 1.0, 'Siniestralidad (fake)', 1.0),
        ])
    );

    $assessment = $service->assessment($route());

    expect($assessment['score'])->toBeNull()
        ->and($assessment['sources']['accidents']['available'])->toBeFalse();
});

test('señales expone la señal real de infraestructura de fase 1', function () use ($route, $osmWith) {
    $route = $route();
    $route['cicloruta_safety_index'] = 0.7;

    $service = new SafetyScoreService($osmWith([
        new SafetySignal('road_type', 20, 1.0, 'OpenStreetMap (fake)', 1.0),
        new SafetySignal('speed', 40, 1.0, 'OpenStreetMap (fake)', 1.0),
        new SafetySignal('cycleway', 90, 1.0, 'OpenStreetMap (fake)', 1.0),
    ]));

    $signals = $service->signals($route);

    expect($signals)->toHaveKey('infrastructure')
        ->and($signals)->toHaveKey('road_type')
        ->and($signals)->toHaveKey('speed')
        ->and($signals)->toHaveKey('cicloruta_safety_index')
        ->and($signals['cicloruta_safety_index'])->toBe(0.7);
});