<x-guest-layout>
    <div class="cv-card p-8 sm:p-10">
        <h2 class="text-2xl font-extrabold text-[#f1fff9]">Recuperar contraseña</h2>
        <p class="mt-1.5 text-sm text-[#a7b8b2]">
            {{ __('¿Olvidaste tu contraseña? No hay problema. Indícanos tu correo y te enviaremos un enlace para restablecerla.') }}
        </p>

        <!-- Session Status -->
        <x-auth-session-status class="mb-4 mt-6" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5">
            @csrf

            <!-- Email Address -->
            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input id="email" class="mt-1.5 block w-full" type="email" name="email" :value="old('email')" required autofocus />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <x-primary-button class="w-full justify-center">
                    {{ __('Enviar enlace') }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-guest-layout>