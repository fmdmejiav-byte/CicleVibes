<?php

use App\Models\Rol;
use App\Models\User;
use Database\Seeders\RolSeeder;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $this->seed(RolSeeder::class);

    $response = $this->post('/register', [
        'nombre' => 'Juan',
        'apellido' => 'Pérez',
        'telefono' => '3001234567',
        'email' => 'juan@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'accept_terms' => 1,
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = auth()->user();

    $this->assertSame('Juan', $user->nombre);
    $this->assertSame('Pérez', $user->apellido);
    $this->assertSame(Rol::where('nombre', 'Usuario')->value('id'), $user->rol_id);
    $this->assertSame('Usuario', $user->rol->nombre);
});

test('registration requires nombre, apellido and email unique', function () {
    $this->seed(RolSeeder::class);

    $this->post('/register', [
        'nombre' => '',
        'apellido' => '',
        'email' => 'juan@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'accept_terms' => 1,
    ])->assertSessionHasErrors(['nombre', 'apellido']);

    User::factory()->create(['email' => 'juan@example.com']);

    $this->post('/register', [
        'nombre' => 'Ana',
        'apellido' => 'López',
        'email' => 'juan@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'accept_terms' => 1,
    ])->assertSessionHasErrors(['email']);
});
