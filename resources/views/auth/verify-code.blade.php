<x-guest-layout>
    @php($expiresAt = (int) session('password_reset_code_expires_at', 0))
    @php($remaining = max(0, $expiresAt - now()->timestamp))

    <div class="cv-card p-8 sm:p-10">
        <h2 class="text-2xl font-extrabold text-[#f1fff9]">Verifica tu correo</h2>
        <p class="mt-1.5 text-sm text-[#a7b8b2]">
            Te enviamos un código de <span class="font-bold text-[#00ff88]">6 dígitos</span> a
            <span class="font-bold text-[#f1fff9]">{{ session('password_reset_email') }}</span>.
            Introdúcelo para continuar. El código expira en {{ \App\Services\PasswordResetService::CODE_TTL_MINUTES }} minutos.
        </p>

        <!-- Session Status -->
        <x-auth-session-status class="mb-4 mt-6" :status="session('status')" />

        @if ($errors->has('code'))
            <div class="mt-6 flex items-start gap-3 rounded-xl border border-[rgba(255,77,90,0.3)] bg-[rgba(255,77,90,0.1)] p-3.5 text-sm text-[#ffb3b8] animate-fade-in">
                <svg class="mt-0.5 h-5 w-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle><path d="M12 8v4M12 16h.01"></path>
                </svg>
                <p>{{ $errors->first('code') }}</p>
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('password.verify.store') }}"
            class="mt-6 space-y-5"
            x-data="{ remaining: {{ $remaining }}, init() { let self = this; this.timer = setInterval(() => { self.remaining = Math.max(0, self.remaining - 1); if (self.remaining === 0) clearInterval(self.timer); }, 1000); } }"
        >
            @csrf

            <!-- Code -->
            <div>
                <x-input-label for="code" value="Código de seguridad" class="font-semibold" />
                <x-text-input
                    id="code"
                    class="mt-1.5 block w-full text-center text-2xl tracking-[0.5em]"
                    type="text"
                    name="code"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="6"
                    pattern="[0-9]{6}"
                    placeholder="••••••"
                    required
                    autofocus
                />
                <x-input-error :messages="$errors->get('code')" class="mt-2" />
            </div>

            <div>
                <x-primary-button class="w-full justify-center">
                    Verificar código
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('password.verify.resend') }}" class="mt-4">
            @csrf
            <button type="submit" class="link-neon w-full text-center text-sm font-semibold">
                Reenviar código
            </button>
        </form>

        <p class="mt-6 text-center text-xs text-[#6f817a]" x-cloak>
            <span x-show="remaining > 0">
                El código expira en
                <span class="font-semibold text-[#00ff88]" x-text="`${Math.floor(remaining / 60)}:${String(remaining % 60).padStart(2, '0')}`"></span>
            </span>
            <span x-show="remaining === 0">
                El código ha expirado.
                <a href="{{ route('password.request') }}" class="link-neon font-semibold">Solicita uno nuevo</a>.
            </span>
        </p>

        <p class="mt-6 text-center text-sm text-[#a7b8b2]">
            ¿No eres tú?
            <a href="{{ route('login') }}" class="link-neon">Volver al inicio de sesión</a>
        </p>
    </div>
</x-guest-layout>