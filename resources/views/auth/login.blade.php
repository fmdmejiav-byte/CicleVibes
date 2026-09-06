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

        <h2 class="text-2xl font-extrabold text-[#f1fff9]">Iniciar sesión</h2>
        <p class="mt-1.5 text-sm text-[#a7b8b2]">Bienvenido de nuevo, sigue pedaleando con nosotros.</p>

        <!-- Session Status -->
        <x-auth-session-status class="mb-4 mt-6" :status="session('status')" />

        <!-- Error summary -->
        @if ($errors->has('email') || $errors->has('password'))
            <div class="mt-6 flex items-start gap-3 rounded-xl border border-[rgba(255,77,90,0.3)] bg-[rgba(255,77,90,0.1)] p-3.5 text-sm text-[#ffb3b8] animate-fade-in">
                <svg class="mt-0.5 h-5 w-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle><path d="M12 8v4M12 16h.01"></path>
                </svg>
                <div>
                    <p class="font-bold text-[#ff8a93]">No pudimos iniciar sesión</p>
                    <p class="mt-0.5 text-[#ffb3b8]">Revisa tu correo electrónico y contraseña e inténtalo de nuevo.</p>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5" x-data="{ show: false, loading: false, remember: false, termsOpen: false }" @submit="loading = true">
            @csrf

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
                        autofocus
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
                <div class="flex items-center justify-between">
                    <label for="password" class="block text-sm font-semibold text-[#a7b8b2]">Contraseña</label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="link-neon text-xs">
                            ¿Olvidaste tu contraseña?
                        </a>
                    @endif
                </div>
                <div class="relative mt-1.5">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-[#6f817a]">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    </span>
                    <input
                        id="password"
                        :type="show ? 'text' : 'password'"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
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

            <!-- Remember me -->
            <label class="flex items-center gap-2.5 select-none">
                <input
                    type="checkbox"
                    name="remember"
                    @change="remember = $event.target.checked"
                    class="h-4 w-4 rounded border-[rgba(0,255,136,0.3)] bg-[rgba(9,24,20,0.6)] text-[#00ff88] accent-[#00ff88] focus:ring-[#00ff88]/40"
                >
                <span class="text-sm text-[#a7b8b2]">Recordarme en este dispositivo</span>
            </label>

            <!-- Submit -->
            <div>
                <button
                    type="submit"
                    :disabled="loading"
                    class="cv-neon-button w-full"
                >
                    <span x-show="!loading" class="inline-flex items-center gap-2">
                        Iniciar sesión
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"></path></svg>
                    </span>
                    <span x-show="loading" x-cloak class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity="0.25"></circle><path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path></svg>
                        Iniciando sesión…
                    </span>
                </button>
            </div>

            <!-- Acceso a Términos y Política de Datos -->
            <p class="text-center text-xs leading-relaxed text-[#6f817a]">
                Al iniciar sesión, los datos de tu cuenta se tratan conforme a la
                <button type="button" @click="termsOpen = true" class="link-neon inline font-semibold">Política de Tratamiento de Datos</button>
                y a los
                <button type="button" @click="termsOpen = true" class="link-neon inline font-semibold">Términos y Condiciones</button>.
            </p>

            @include('auth.partials._terms-modal')
        </form>

        <p class="mt-8 text-center text-sm text-[#a7b8b2]">
            ¿No tienes una cuenta?
            <a href="{{ route('register') }}" class="link-neon">
                Regístrate
            </a>
        </p>
    </div>
</x-guest-layout>