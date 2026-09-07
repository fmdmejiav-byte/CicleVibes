<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetCodeVerifyController extends Controller
{
    public function __construct(private readonly PasswordResetService $service)
    {
    }

    public function show(Request $request): View|RedirectResponse
    {
        if (! is_string($request->session()->get('password_reset_email'))) {
            return redirect()->route('password.request');
        }

        return view('auth.verify-code');
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        $email = $request->session()->get('password_reset_email');

        if (! is_string($email)) {
            return redirect()
                ->route('password.request')
                ->withErrors(['email' => __('La sesión ha caducado. Solicita un nuevo código.')]);
        }

        $key = 'password-reset-verify:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()->withErrors(['code' => __('Demasiados intentos de verificación. Espera un momento e inténtalo de nuevo.')]);
        }

        RateLimiter::hit($key, 60);

        $user = User::where('email', $email)->first();

        if (! $user || ! $user->hasPassword()) {
            return back()->withErrors(['code' => __('El código recibido no es válido o ha expirado. Solicita uno nuevo.')]);
        }

        $result = $this->service->verify($user, $request->code);

        if ($result === 'valid') {
            $request->session()->put([
                'password_reset_verified' => true,
                'password_reset_verified_at' => now()->timestamp,
            ]);

            return redirect()->route('password.reset');
        }

        if ($result === 'expired' || $result === 'locked') {
            return $this->resetAndRedirect($request, $result === 'expired' ? 'expirado' : 'intentos');
        }

        return back()->withErrors(['code' => __('El código recibido no es válido o ha expirado. Solicita uno nuevo.')]);
    }

    public function resend(Request $request): RedirectResponse
    {
        $email = $request->session()->get('password_reset_email');

        if (! is_string($email)) {
            return redirect()->route('password.request');
        }

        $key = 'password-reset-request:'.$request->ip().'|'.Str::lower($email);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => __('Demasiadas solicitudes. Espera un momento.')]);
        }

        RateLimiter::hit($key, 60);

        $user = User::where('email', $email)->first();

        $request->session()->put('password_reset_code_expires_at', now()->addMinutes(PasswordResetService::CODE_TTL_MINUTES)->timestamp);
        $request->session()->forget(['password_reset_verified', 'password_reset_verified_at']);

        if ($user && $user->hasPassword()) {
            $this->service->createAndSend($user);
        }

        return back()->with('status', __('Te hemos enviado un nuevo código de seguridad a tu correo si existe una cuenta asociada.'));
    }

    private function resetAndRedirect(Request $request, string $reason): RedirectResponse
    {
        $request->session()->forget([
            'password_reset_email',
            'password_reset_code_expires_at',
            'password_reset_verified',
            'password_reset_verified_at',
        ]);

        $message = $reason === 'expirado'
            ? __('El código ha expirado. Solicita uno nuevo para continuar.')
            : __('Has superado el número de intentos. Solicita un nuevo código para continuar.');

        return redirect()->route('password.request')->withErrors(['email' => $message]);
    }
}