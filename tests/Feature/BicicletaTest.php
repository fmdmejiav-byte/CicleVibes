<?php

use App\Enums\EstadoBicicleta;
use App\Models\Bicicleta;
use App\Models\User;

test('una bicicleta puede crearse con su factory y su estado por defecto', function () {
    $bicicleta = Bicicleta::factory()->create();

    expect($bicicleta->user)->toBeInstanceOf(User::class)
        ->and($bicicleta->tipoBicicleta->exists)->toBeTrue()
        ->and($bicicleta->barrio->exists)->toBeTrue()
        ->and($bicicleta->estado)->toBe(EstadoBicicleta::Activa);
});

test('la bicicleta pertenece al usuario correcto', function () {
    $usuario = User::factory()->create();

    $bicicleta = Bicicleta::factory()->create([
        'user_id' => $usuario->id,
    ]);

    expect($bicicleta->user->is($usuario))->toBeTrue()
        ->and($usuario->bicicletas)->toHaveCount(1);
});
