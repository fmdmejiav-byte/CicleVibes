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

class PasswordResetLinkController extends Controller
{
    public function __construct(private readonly PasswordResetService $service)
    {
    }

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = Str::lower(trim($request->email));

        $key = 'password-reset-request:'.$request->ip().'|'.$email;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()
                ->withErrors(['email' => __('Demasiados intentos. Espera un momento antes de volver a intentarlo.')])
                ->withInput($request->only('email'));
        }

        RateLimiter::hit($key, 60);

        $user = User::where('email', $email)->first();

        $request->session()->put([
            'password_reset_email' => $email,
            'password_reset_code_expires_at' => now()->addMinutes(PasswordResetService::CODE_TTL_MINUTES)->timestamp,
        ]);
        $request->session()->forget(['password_reset_verified', 'password_reset_verified_at']);

        if ($user && $user->hasPassword()) {
            $this->service->createAndSend($user);
        }

        return redirect()
            ->route('password.verify')
            ->with('status', __('Si existe una cuenta asociada a este correo, recibirás un código de seguridad para restablecer tu contraseña.'));
    }
}