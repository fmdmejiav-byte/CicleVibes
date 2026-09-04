<x-guest-layout>
    <div class="cv-card p-8 sm:p-10">
        <h2 class="text-2xl font-extrabold text-[#f1fff9]">Confirma tu contraseña</h2>
        <p class="mt-1.5 text-sm text-[#a7b8b2]">
            {{ __('Esta es un área segura de la aplicación. Confirma tu contraseña antes de continuar.') }}
        </p>

        <form method="POST" action="{{ route('password.confirm') }}" class="mt-6 space-y-5">
            @csrf

            <!-- Password -->
            <div>
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input id="password" class="mt-1.5 block w-full" type="password" name="password" required autocomplete="current-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <x-primary-button class="w-full justify-center">
                    {{ __('Confirmar') }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-guest-layout>