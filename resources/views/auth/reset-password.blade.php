<x-guest-layout>
    <div class="cv-card p-8 sm:p-10">
        <h2 class="text-2xl font-extrabold text-[#f1fff9]">Restablecer contraseña</h2>
        <p class="mt-1.5 text-sm text-[#a7b8b2]">Define una nueva contraseña para tu cuenta.</p>

        <form method="POST" action="{{ route('password.store') }}" class="mt-6 space-y-5">
            @csrf

            <!-- Password Reset Token -->
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <!-- Email Address -->
            <div>
                <x-input-label for="email" value="Correo electrónico" class="font-semibold" />
                <x-text-input id="email" class="mt-1.5 block w-full" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" placeholder="tucorreo@ejemplo.com" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <!-- Password -->
            <div>
                <x-input-label for="password" value="Nueva contraseña" class="font-semibold" />
                <x-text-input id="password" class="mt-1.5 block w-full" type="password" name="password" required autocomplete="new-password" placeholder="Mínimo 8 caracteres" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <!-- Confirm Password -->
            <div>
                <x-input-label for="password_confirmation" value="Confirmar nueva contraseña" class="font-semibold" />
                <x-text-input id="password_confirmation" class="mt-1.5 block w-full" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Repite tu contraseña" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <div>
                <x-primary-button class="w-full justify-center">
                    Restablecer contraseña
                </x-primary-button>
            </div>
        </form>
    </div>
</x-guest-layout>