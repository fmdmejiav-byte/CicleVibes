<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Solo se envía el enlace si la cuenta tiene contraseña local. Las
        // cuentas creadas con "Continuar con Google" no tienen contraseña y
        // no deben recibir un enlace de restablecimiento. La respuesta es
        // siempre la misma para no revelar si el correo está registrado.
        $user = User::where('email', $request->email)->first();

        if ($user && $user->hasPassword()) {
            $status = Password::sendResetLink($request->only('email'));

            if ($status !== Password::RESET_LINK_SENT) {
                Log::warning('No se pudo enviar el enlace de restablecimiento de contraseña', [
                    'email' => $request->email,
                    'status' => $status,
                ]);
            }
        }

        return back()->with('status', __('Si existe una cuenta asociada a este correo, recibirás un enlace para restablecer tu contraseña.'));
    }
}
