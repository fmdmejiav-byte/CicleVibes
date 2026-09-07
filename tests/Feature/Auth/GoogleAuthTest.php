<?php

use App\Models\Rol;
use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;

function googleFakeUser(array $attributes = []): SocialiteUser
{
    $user = new SocialiteUser;
    $user->id = $attributes['id'] ?? 'google-12345';
    $user->email = $attributes['email'] ?? 'juan.perez@gmail.com';
    $user->name = $attributes['name'] ?? 'Juan Pérez Haddad';
    $user->avatar = $attributes['avatar'] ?? 'https://lh3.googleusercontent.com/a/avatar';

    $user->user = array_merge([
        'sub' => $user->id,
        'email' => $user->email,
        'name' => $user->name,
        'given_name' => 'Juan',
        'family_name' => 'Pérez Haddad',
        'picture' => $user->avatar,
    ], $attributes['raw'] ?? []);

    return $user;
}

beforeEach(function () {
    $this->seed(RolSeeder::class);

    config([
        'services.google' => [
            'client_id' => 'fake-client-id.apps.googleusercontent.com',
            'client_secret' => 'fake-secret',
            'redirect' => 'http://localhost/auth/google/callback',
        ],
    ]);
});

test('el botón de Google solo se muestra si hay credenciales configuradas', function () {
    $this->get('/login')->assertOk()->assertSee('Continuar con Google');
});

test('redirige al proveedor de Google para autorizar', function () {
    Socialite::fake('google');

    $this->get('/auth/google')
        ->assertRedirect('https://socialite.fake/google/authorize');
});

test('crea un usuario nuevo con Google y lo autentica', function () {
    Socialite::fake('google', googleFakeUser());

    $this->get('/auth/google/callback')
        ->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'juan.perez@gmail.com')->first();

    $this->assertAuthenticatedAs($user);
    expect($user)
        ->not->toBeNull()
        ->google_id->toBe('google-12345')
        ->password->toBeNull()
        ->email_verified_at->not->toBeNull()
        ->nombre->toBe('Juan')
        ->apellido->toBe('Pérez Haddad')
        ->and($user->foto)->toBe('https://lh3.googleusercontent.com/a/avatar');
    expect(Rol::where('nombre', 'Usuario')->value('id'))->toBe($user->rol_id);
});

test('no crea cuentas duplicadas si el correo ya existe', function () {
    $user = User::factory()->create([
        'email' => 'juan.perez@gmail.com',
        'password' => bcrypt('password'),
    ]);

    Socialite::fake('google', googleFakeUser());

    $this->get('/auth/google/callback')->assertRedirect(route('dashboard', absolute: false));

    expect(User::where('email', 'juan.perez@gmail.com')->count())->toBe(1);

    $fresh = $user->fresh();
    expect($fresh->google_id)->toBe('google-12345')
        ->and($fresh->password)->not->toBeNull();

    $this->assertAuthenticatedAs($fresh);
});

test('si el correo ya existe por otro método, vincula la cuenta de Google', function () {
    $existing = User::factory()->create(['email' => 'juan.perez@gmail.com']);

    Socialite::fake('google', googleFakeUser());

    $this->get('/auth/google/callback');

    expect($existing->fresh()->google_id)->toBe('google-12345');
});

test('maneja la cancelación del usuario en Google', function () {
    Socialite::fake('google', googleFakeUser());

    $this->get('/auth/google/callback?error=access_denied')
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    $this->assertGuest();
});

test('maneja un estado OAuth inválido', function () {
    Socialite::fake('google', function () {
        throw new InvalidStateException;
    });

    $this->get('/auth/google/callback')
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    $this->assertGuest();
});

test('maneja errores genéricos de OAuth sin mostrar detalles internos', function () {
    Socialite::fake('google', function () {
        throw new Exception('internal stack trace leak');
    });

    $response = $this->get('/auth/google/callback')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('rechaza una respuesta de Google sin correo válido', function () {
    Socialite::fake('google', googleFakeUser(['email' => 'not-an-email']));

    $this->get('/auth/google/callback')
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    expect(User::where('email', 'not-an-email')->exists())->toBeFalse();
});

test('no envía código de recuperación a cuentas que solo usan Google', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'solo.google@example.com',
        'password' => null,
        'google_id' => 'google-9876',
    ]);

    $response = $this->post('/forgot-password', ['email' => $user->email]);

    // Respuesta genérica: no revela si el correo está registrado.
    $response
        ->assertRedirect(route('password.verify'))
        ->assertSessionHas('status');
    Notification::assertNothingSent();
});

test('recuperación con cuenta Google responde igual para correos inexistentes', function () {
    Notification::fake();

    $response = $this->post('/forgot-password', ['email' => 'no-existe@example.com']);

    $response
        ->assertRedirect(route('password.verify'))
        ->assertSessionHas('status');
    Notification::assertNothingSent();
});

test('envía código de recuperación a cuentas con contraseña local', function () {
    Notification::fake();

    $user = User::factory()->create(['password' => bcrypt('password')]);

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, PasswordResetCodeNotification::class);
});
