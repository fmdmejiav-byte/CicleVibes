<x-guest-layout>
    <div class="cv-card p-8 sm:p-10">
        <!-- Mobile brand -->
        <div class="mb-8 flex items-center gap-2 lg:hidden">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-[linear-gradient(135deg,#00ff88,#00b3a0)] text-[#02140d]">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="18.5" cy="17.5" r="3.5"></circle>
                    <path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"></path>
                </svg>
            </span>
            <span class="text-xl font-extrabold text-[#f1fff9]">
                Cicle<span class="text-[#00ff88]">Vibes</span>
            </span>
        </div>

        <h2 class="text-2xl font-extrabold text-[#f1fff9]">Crea tu cuenta</h2>
        <p class="mt-1.5 text-sm text-[#a7b8b2]">Únete a la comunidad y empieza a explorar nuevas rutas.</p>

        <!-- Error summary -->
        @if ($errors->any())
            <div class="mt-6 flex items-start gap-3 rounded-xl border border-[rgba(255,77,90,0.3)] bg-[rgba(255,77,90,0.1)] p-3.5 text-sm text-[#ffb3b8] animate-fade-in">
                <svg class="mt-0.5 h-5 w-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle><path d="M12 8v4M12 16h.01"></path>
                </svg>
                <div>
                    <p class="font-bold text-[#ff8a93]">Revisa los siguientes campos</p>
                    <p class="mt-0.5 text-[#ffb3b8]">Por favor corrige los errores marcados para continuar.</p>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-5" x-data="{ show: false, showConfirm: false, loading: false, accepted: false, termsOpen: false }" @submit="loading = true">
            @csrf

            <!-- Nombre -->
            <div>
                <label for="nombre" class="block text-sm font-semibold text-[#a7b8b2]">Nombre</label>
                <div class="relative mt-1.5">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-[#6f817a]">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    </span>
                    <input
                        id="nombre"
                        type="text"
                        name="nombre"
                        :value="old('nombre')"
                        required
                        autofocus
                        autocomplete="given-name"
                        placeholder="Tu nombre"
                        class="cv-input block w-full rounded-xl border px-4 py-2.5 pl-11 pr-4 text-sm text-[#f1fff9] shadow-sm @error('nombre') border-[#ff4d5a] @enderror"
                    >
                </div>
                @error('nombre')
                    <p class="mt-1.5 text-sm text-[#ff8a93]">{{ $message }}</p>
                @enderror
            </div>

            <!-- Apellido -->
            <div>
                <label for="apellido" class="block text-sm font-semibold text-[#a7b8b2]">Apellido</label>
                <div class="relative mt-1.5">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-[#6f817a]">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    </span>
                    <input
                        id="apellido"
                        type="text"
                        name="apellido"
                        :value="old('apellido')"
                        required
                        autocomplete="family-name"
                        placeholder="Tu apellido"
                        class="cv-input block w-full rounded-xl border px-4 py-2.5 pl-11 pr-4 text-sm text-[#f1fff9] shadow-sm @error('apellido') border-[#ff4d5a] @enderror"
                    >
                </div>
                @error('apellido')
                    <p class="mt-1.5 text-sm text-[#ff8a93]">{{ $message }}</p>
                @enderror
            </div>

            <!-- Email -->
            <div>
                <label for="email" class="block text-sm font-semibold text-[#a7b8b2]">Correo electrónico</label>
                <div class="relative mt-1.5">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-[#6f817a]">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>
                    </span>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        :value="old('email')"
                        required
                        autocomplete="username"
                        placeholder="tucorreo@ejemplo.com"
                        class="cv-input block w-full rounded-xl border px-4 py-2.5 pl-11 pr-4 text-sm text-[#f1fff9] shadow-sm @error('email') border-[#ff4d5a] @enderror"
                    >
                </div>
                @error('email')
                    <p class="mt-1.5 text-sm text-[#ff8a93]">{{ $message }}</p>
                @enderror
            </div>

            <!-- Password -->
            <div>
                <label for="password" class="block text-sm font-semibold text-[#a7b8b2]">Contraseña</label>
                <div class="relative mt-1.5">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-[#6f817a]">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    </span>
                    <input
                        id="password"
                        :type="show ? 'text' : 'password'"
                        name="password"
                        required
                        autocomplete="new-password"
                        placeholder="Mínimo 8 caracteres"
                        class="cv-input block w-full rounded-xl border px-4 py-2.5 pl-11 pr-11 text-sm text-[#f1fff9] shadow-sm @error('password') border-[#ff4d5a] @enderror"
                    >
                    <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-[#6f817a] transition hover:text-[#00ff88]" :aria-label="show ? 'Ocultar contraseña' : 'Mostrar contraseña'">
                        <svg x-show="!show" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        <svg x-show="show" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path><line x1="2" x2="22" y1="2" y2="22"></line></svg>
                    </button>
                </div>
                @error('password')
                    <p class="mt-1.5 text-sm text-[#ff8a93]">{{ $message }}</p>
                @enderror
            </div>

            <!-- Confirm Password -->
            <div>
                <label for="password_confirmation" class="block text-sm font-semibold text-[#a7b8b2]">Confirmar contraseña</label>
                <div class="relative mt-1.5">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-[#6f817a]">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    </span>
                    <input
                        id="password_confirmation"
                        :type="showConfirm ? 'text' : 'password'"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="Repite tu contraseña"
                        class="cv-input block w-full rounded-xl border px-4 py-2.5 pl-11 pr-11 text-sm text-[#f1fff9] shadow-sm @error('password') border-[#ff4d5a] @enderror"
                    >
                    <button type="button" @click="showConfirm = !showConfirm" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-[#6f817a] transition hover:text-[#00ff88]" :aria-label="showConfirm ? 'Ocultar contraseña' : 'Mostrar contraseña'">
                        <svg x-show="!showConfirm" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        <svg x-show="showConfirm" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path><line x1="2" x2="22" y1="2" y2="22"></line></svg>
                    </button>
                </div>
            </div>

            <!-- Aceptación de términos y política de datos -->
            <div class="rounded-xl border border-[rgba(0,229,255,0.2)] bg-[rgba(0,229,255,0.05)] p-3.5">
                <label class="flex cursor-pointer items-start gap-3 select-none">
                    <input
                        type="checkbox"
                        name="accept_terms"
                        value="1"
                        x-model="accepted"
                        required
                        class="mt-0.5 h-5 w-5 rounded border-[rgba(0,255,136,0.3)] bg-[rgba(9,24,20,0.6)] text-[#00ff88] accent-[#00ff88] focus:ring-[#00ff88]/40"
                    >
                    <span class="text-sm leading-snug text-[#a7b8b2]">
                        He leído y acepto los
                        <button type="button" @click="termsOpen = true" class="link-neon inline font-semibold">Términos y Condiciones</button>
                        y la
                        <button type="button" @click="termsOpen = true" class="link-neon inline font-semibold">Política de Tratamiento de Datos</button>.
                    </span>
                </label>
            </div>

            <!-- Submit -->
            <div>
                <button
                    type="submit"
                    :disabled="loading || !accepted"
                    class="cv-neon-button w-full"
                    :class="(!accepted) ? '!opacity-45 !shadow-none' : ''"
                >
                    <span x-show="!loading" class="inline-flex items-center gap-2">
                        Crear cuenta
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"></path></svg>
                    </span>
                    <span x-show="loading" x-cloak class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity="0.25"></circle><path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path></svg>
                        Creando cuenta…
                    </span>
                </button>
            </div>

            @include('auth.partials._terms-modal')
        </form>

        <p class="mt-8 text-center text-sm text-[#a7b8b2]">
            ¿Ya tienes una cuenta?
            <a href="{{ route('login') }}" class="link-neon">
                Inicia sesión
            </a>
        </p>
    </div>
</x-guest-layout>