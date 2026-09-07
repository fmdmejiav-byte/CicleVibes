<x-guest-layout>
    <div class="cv-card p-8 sm:p-10">
        <h2 class="text-2xl font-extrabold text-[#f1fff9]">Recuperar contraseña</h2>
        <p class="mt-1.5 text-sm text-[#a7b8b2]">
            {{ __('¿Olvidaste tu contraseña? No hay problema. Indícanos tu correo y te enviaremos un código de seguridad de 6 dígitos para restablecerla.') }}
        </p>

        <!-- Session Status -->
        <x-auth-session-status class="mb-4 mt-6" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5">
            @csrf

            <!-- Email Address -->
            <div>
                <x-input-label for="email" value="Correo electrónico" class="font-semibold" />
                <x-text-input id="email" class="mt-1.5 block w-full" type="email" name="email" :value="old('email')" required autofocus placeholder="tucorreo@ejemplo.com" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <x-primary-button class="w-full justify-center">
                    Enviar código de recuperación
                </x-primary-button>
            </div>
        </form>
    </div>
</x-guest-layout>