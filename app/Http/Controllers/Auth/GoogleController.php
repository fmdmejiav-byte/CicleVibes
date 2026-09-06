<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleController extends Controller
{
    /**
     * Redirige al usuario a Google para autorizar el acceso.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    /**
     * Procesa la respuesta de Google tras la autorización.
     */
    public function callback(Request $request): RedirectResponse
    {
        // El usuario canceló la autorización en Google.
        if ($request->query('error')) {
            return redirect()->route('login')
                ->with('error', 'Cancelaste el inicio de sesión con Google. Puedes volver a intentarlo cuando quieras.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            // Estado OAuth no válido (posible ataque CSRF o respuesta duplicada).
            return redirect()->route('login')
                ->with('error', 'La sesión de autenticación con Google no es válida. Inténtalo de nuevo.');
        } catch (Exception $e) {
            Log::warning('Error de autenticación con Google', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('login')
                ->with('error', 'No pudimos completar el inicio de sesión con Google. Inténtalo de nuevo más tarde.');
        }

        // Solo se usa la información autorizada: identificador, nombre y correo.
        $email = strtolower(trim((string) $googleUser->getEmail()));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->route('login')
                ->with('error', 'No pudimos obtener un correo válido desde tu cuenta de Google.');
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            // Cuenta existente: se vincula Google sin crear duplicados.
            if ($user->google_id !== $googleUser->getId()) {
                $user->forceFill(['google_id' => $googleUser->getId()])->save();
            }
        } else {
            // Cuenta nueva: se crea únicamente con los datos autorizados.
            $this->createGoogleUser($googleUser, $email);
            $user = User::where('email', $email)->first();
        }

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Crea un usuario nuevo a partir de la cuenta de Google.
     */
    private function createGoogleUser(mixed $googleUser, string $email): void
    {
        $usuarioRol = Rol::firstOrCreate(['nombre' => 'Usuario']);

        User::create([
            'rol_id' => $usuarioRol->id,
            'nombre' => $this->nombreDesdeGoogle($googleUser),
            'apellido' => $this->apellidoDesdeGoogle($googleUser),
            'foto' => $this->avatarValido($googleUser->getAvatar()) ? $googleUser->getAvatar() : null,
            'email' => $email,
            'email_verified_at' => now(),
            'google_id' => $googleUser->getId(),
            'password' => null,
        ]);
    }

    /**
     * Obtiene el nombre del usuario desde la información autorizada de Google.
     */
    private function nombreDesdeGoogle(mixed $googleUser): string
    {
        $raw = $googleUser->getRaw();
        $nombre = trim((string) ($raw['given_name'] ?? ''));

        if ($nombre === '') {
            $nombre = $this->obtenerNombreCompleto($googleUser)[0];
        }

        return $nombre !== '' ? $nombre : 'Usuario';
    }

    /**
     * Obtiene el apellido del usuario desde la información autorizada de Google.
     */
    private function apellidoDesdeGoogle(mixed $googleUser): string
    {
        $raw = $googleUser->getRaw();
        $apellido = trim((string) ($raw['family_name'] ?? ''));

        if ($apellido === '') {
            $partes = array_slice($this->obtenerNombreCompleto($googleUser), 1);
            $apellido = implode(' ', $partes);
        }

        return $apellido;
    }

    /**
     * Descompone el nombre completo proporcionado por Google.
     *
     * @return array<int, string>
     */
    private function obtenerNombreCompleto(mixed $googleUser): array
    {
        $completo = trim((string) $googleUser->getName());

        if ($completo === '') {
            return [];
        }

        return preg_split('/\s+/', $completo) ?: [$completo];
    }

    /**
     * Comprueba que el avatar sea una URL http(s) válida.
     */
    private function avatarValido(?string $avatar): bool
    {
        return $avatar !== null
            && filter_var($avatar, FILTER_VALIDATE_URL) !== false
            && str_starts_with($avatar, 'https://');
    }
}
