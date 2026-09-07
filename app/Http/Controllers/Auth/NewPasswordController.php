<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PasswordResetService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function __construct(private readonly PasswordResetService $service)
    {
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (! $this->isAuthorized($request)) {
            return redirect()
                ->route('password.request')
                ->withErrors(['email' => __('La sesión ha caducado. Solicita un nuevo código.')]);
        }

        return view('auth.reset-password');
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->isAuthorized($request)) {
            return redirect()
                ->route('password.request')
                ->withErrors(['email' => __('La sesión ha caducado. Solicita un nuevo código.')]);
        }

        $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $email = $request->session()->get('password_reset_email');

        $user = User::where('email', $email)->first();

        if (! $user) {
            return redirect()
                ->route('password.request')
                ->withErrors(['email' => __('No fue posible restablecer la contraseña. Solicita un nuevo código.')]);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ])->save();

        event(new PasswordReset($user));

        $this->service->revokeActiveCodes($user);

        $request->session()->forget([
            'password_reset_email',
            'password_reset_code_expires_at',
            'password_reset_verified',
            'password_reset_verified_at',
        ]);

        return redirect()
            ->route('login')
            ->with('status', __('Tu contraseña fue restablecida correctamente. Ya puedes iniciar sesión.'));
    }

    private function isAuthorized(Request $request): bool
    {
        if ($request->session()->get('password_reset_verified') !== true) {
            return false;
        }

        if (! is_string($request->session()->get('password_reset_email'))) {
            return false;
        }

        $verifiedAt = (int) $request->session()->get('password_reset_verified_at', 0);

        return now()->timestamp - $verifiedAt <= PasswordResetService::VERIFIED_TTL_SECONDS;
    }
}