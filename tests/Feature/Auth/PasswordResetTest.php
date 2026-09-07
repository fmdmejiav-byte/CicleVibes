<?php

use App\Models\PasswordResetCode;
use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use App\Services\PasswordResetService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

function makeResetCode(User $user, string $code, array $overrides = []): PasswordResetCode
{
    return PasswordResetCode::create(array_merge([
        'user_id' => $user->id,
        'code_hash' => Hash::make($code),
        'expires_at' => now()->addMinutes(10),
        'attempts' => 0,
    ], $overrides));
}

function verifySessionFor(User $user): void
{
    test()->withSession([
        'password_reset_email' => $user->email,
        'password_reset_code_expires_at' => now()->addMinutes(10)->timestamp,
    ]);
}

beforeEach(function () {
    Notification::fake();
});

test('la pantalla de recuperación de contraseña se puede renderizar', function () {
    $this->get('/forgot-password')->assertStatus(200);
});

test('solicitar un código genera un registro con hash y envía la notificación', function () {
    $user = User::factory()->create();

    $response = $this->post('/forgot-password', ['email' => $user->email]);

    $response->assertRedirect(route('password.verify'));

    $record = PasswordResetCode::where('user_id', $user->id)->latest('id')->first();

    expect($record)
        ->not->toBeNull()
        ->expires_at->toBeGreaterThan(now()->addMinutes(9))
        ->attempts->toBe(0)
        ->used_at->toBeNull()
        ->revoked->toBeFalse()
        ->and(Hash::isHashed($record->code_hash))->toBeTrue();

    Notification::assertSentTo($user, PasswordResetCodeNotification::class);
});

test('no se almacena el código en texto plano', function () {
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    $record = PasswordResetCode::where('user_id', $user->id)->first();

    expect($record->code_hash)
        ->not->toBeNull()
        ->not->toMatch('/^\d{6}$/')
        ->toStartWith('$2y$');
});

test('la pantalla de verificación se puede renderizar', function () {
    $user = User::factory()->create();

    verifySessionFor($user);

    $this->get('/verify-code')->assertStatus(200);
});

test('la pantalla de verificación redirige a solicitud si no hay sesión', function () {
    $this->get('/verify-code')->assertRedirect(route('password.request'));
});

test('un código correcto verifica la identidad y permite cambiar la contraseña', function () {
    $user = User::factory()->create();

    makeResetCode($user, '123456');
    verifySessionFor($user);

    $this->post('/verify-code', ['code' => '123456'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('password.reset'));

    $record = PasswordResetCode::where('user_id', $user->id)->first();

    expect($record->used_at)->not->toBeNull();

    $this->post('/reset-password', [
        'password' => 'nueva-clave-segura',
        'password_confirmation' => 'nueva-clave-segura',
    ])->assertRedirect(route('login'));

    expect(Hash::check('nueva-clave-segura', $user->fresh()->password))->toBeTrue();
});

test('un código incorrecto incrementa los intentos', function () {
    $user = User::factory()->create();

    makeResetCode($user, '123456');
    verifySessionFor($user);

    $this->post('/verify-code', ['code' => '000000'])
        ->assertSessionHasErrors('code');

    $record = PasswordResetCode::where('user_id', $user->id)->first();

    expect($record->attempts)->toBe(1);
});

test('un código expirado no puede utilizarse', function () {
    $user = User::factory()->create();

    makeResetCode($user, '123456', ['expires_at' => now()->subMinute()]);
    verifySessionFor($user);

    $this->post('/verify-code', ['code' => '123456'])
        ->assertRedirect(route('password.request'))
        ->assertSessionHasErrors('email');

    $record = PasswordResetCode::where('user_id', $user->id)->first();

    expect($record->revoked)->toBeTrue();
});

test('un código utilizado no puede reutilizarse', function () {
    $user = User::factory()->create();

    makeResetCode($user, '123456');
    verifySessionFor($user);

    $this->post('/verify-code', ['code' => '123456'])->assertRedirect(route('password.reset'));

    $this->post('/verify-code', ['code' => '123456'])->assertSessionHasErrors('code');
});

test('agotar el límite de intentos invalida el código', function () {
    $user = User::factory()->create();

    makeResetCode($user, '123456', ['attempts' => PasswordResetService::MAX_ATTEMPTS - 1]);
    verifySessionFor($user);

    $this->post('/verify-code', ['code' => '000000'])
        ->assertRedirect(route('password.request'))
        ->assertSessionHasErrors('email');

    $record = PasswordResetCode::where('user_id', $user->id)->first();

    expect($record->attempts)->toBe(PasswordResetService::MAX_ATTEMPTS)
        ->and($record->revoked)->toBeTrue();
});

test('solicitar un nuevo código invalida el anterior', function () {
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);
    $this->post('/forgot-password', ['email' => $user->email]);

    $codes = PasswordResetCode::where('user_id', $user->id)->orderBy('id')->get();

    expect($codes)->toHaveCount(2)
        ->and($codes->first()->revoked)->toBeTrue()
        ->and($codes->last()->revoked)->toBeFalse();
});

test('no se puede acceder a la pantalla de nueva contraseña sin verificar el código', function () {
    $user = User::factory()->create();

    $this->get('/reset-password')->assertRedirect(route('password.request'));

    verifySessionFor($user);

    $this->get('/reset-password')->assertRedirect(route('password.request'));

    $this->withSession([
        'password_reset_email' => $user->email,
        'password_reset_verified' => false,
    ])->get('/reset-password')->assertRedirect(route('password.request'));
});

test('cambiar la contraseña invalida los códigos y cierra la autorización temporal', function () {
    $user = User::factory()->create();

    makeResetCode($user, '123456');
    verifySessionFor($user);

    $this->post('/verify-code', ['code' => '123456'])->assertRedirect(route('password.reset'));

    $this->post('/reset-password', [
        'password' => 'nueva-clave-segura',
        'password_confirmation' => 'nueva-clave-segura',
    ])->assertRedirect(route('login'));

    $active = PasswordResetCode::where('user_id', $user->id)
        ->where('revoked', false)
        ->whereNull('used_at')
        ->count();

    expect($active)->toBe(0);

    $this->get('/reset-password')->assertRedirect(route('password.request'));
});

test('se limita la cantidad de solicitudes de código', function () {
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])->assertRedirect(route('password.verify'));
    $this->post('/forgot-password', ['email' => $user->email])->assertRedirect(route('password.verify'));
    $this->post('/forgot-password', ['email' => $user->email])->assertRedirect(route('password.verify'));

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertSessionHasErrors('email');
});

test('solicitar un nuevo código vuelve a exigir verificación', function () {
    $user = User::factory()->create();

    makeResetCode($user, '123456');
    verifySessionFor($user);

    $this->post('/verify-code', ['code' => '123456'])->assertRedirect(route('password.reset'));

    $this->post('/verify-code/resend')->assertRedirect();

    $this->get('/reset-password')->assertRedirect(route('password.request'));
});