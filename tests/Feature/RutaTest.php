<?php

use App\Models\Ruta;
use App\Models\User;

test('una ruta puede crearse con su factory', function () {
    $ruta = Ruta::factory()->create();

    expect($ruta->user)->toBeInstanceOf(User::class)
        ->and($ruta->nombre)->not->toBeEmpty()
        ->and($ruta->origen)->not->toBeEmpty()
        ->and($ruta->destino)->not->toBeEmpty();
});

test('la ruta pertenece al usuario correcto', function () {
    $usuario = User::factory()->create();

    $ruta = Ruta::factory()->create([
        'user_id' => $usuario->id,
    ]);

    expect($ruta->user->is($usuario))->toBeTrue()
        ->and($usuario->rutas)->toHaveCount(1);
});
