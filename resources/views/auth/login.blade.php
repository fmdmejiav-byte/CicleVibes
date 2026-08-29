<x-guest-layout>
    <div class="bg-white/90 backdrop-blur-sm rounded-2xl shadow-xl shadow-emerald-900/10 ring-1 ring-emerald-900/5 p-8 sm:p-10">
        <!-- Mobile brand -->
        <div class="mb-8 flex items-center gap-2 lg:hidden">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-600 text-white">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="18.5" cy="17.5" r="3.5"></circle>
                    <path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"></path>
                </svg>
            </span>
            <span class="text-xl font-bold text-gray-900">CicleVibes</span>
        </div>

        <h2 class="text-2xl font-extrabold text-gray-900">Iniciar sesión</h2>
        <p class="mt-1.5 text-sm text-gray-500">Bienvenido de nuevo, sigue pedaleando con nosotros.</p>

        <!-- Session Status -->
        <x-auth-session-status class="mb-4 mt-6" :status="session('status')" />

        <!-- Error summary -->
        @if ($errors->has('email') || $errors->has('password'))
            <div class="mt-6 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-3.5 text-sm text-red-700 animate-fade-in">
                <svg class="mt-0.5 h-5 w-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle><path d="M12 8v4M12 16h.01"></path>
                </svg>
                <div>
                    <p class="font-medium">No pudimos iniciar sesión</p>
                    <p class="mt-0.5 text-red-600/90">Revisa tu correo electrónico y contraseña e inténtalo de nuevo.</p>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5" x-data="{ show: false, loading: false, remember: false }" @submit="loading = true">
            @csrf

            <!-- Email -->
            <div>
                <label for="email" class="block text-sm font-semibold text-gray-700">Correo electrónico</label>
                <div class="relative mt-1.5">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
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
                        class="block w-full rounded-lg border-gray-300 bg-gray-50 pl-11 pr-4 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm transition focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/30 @error('email') border-red-300 @enderror"
                    >
                </div>
                @error('email')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Password -->
            <div>
                <div class="flex items-center justify-between">
                    <label for="password" class="block text-sm font-semibold text-gray-700">Contraseña</label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="text-xs font-medium text-emerald-600 hover:text-emerald-700 hover:underline">
                            ¿Olvidaste tu contraseña?
                        </a>
                    @endif
                </div>
                <div class="relative mt-1.5">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    </span>
                    <input
                        id="password"
                        :type="show ? 'text' : 'password'"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                        class="block w-full rounded-lg border-gray-300 bg-gray-50 pl-11 pr-11 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm transition focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/30 @error('password') border-red-300 @enderror"
                    >
                    <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-400 hover:text-gray-600" :aria-label="show ? 'Ocultar contraseña' : 'Mostrar contraseña'">
                        <svg x-show="!show" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        <svg x-show="show" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path><line x1="2" x2="22" y1="2" y2="22"></line></svg>
                    </button>
                </div>
                @error('password')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Remember me -->
            <label class="flex items-center gap-2.5 select-none">
                <input
                    type="checkbox"
                    name="remember"
                    @change="remember = $event.target.checked"
                    class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500/40"
                >
                <span class="text-sm text-gray-600">Recordarme en este dispositivo</span>
            </label>

            <!-- Submit -->
            <div>
                <button
                    type="submit"
                    :disabled="loading"
                    class="group relative flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-3 text-sm font-bold text-white shadow-md shadow-emerald-600/25 transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70"
                >
                    <span x-show="!loading" class="inline-flex items-center gap-2">
                        Iniciar sesión
                        <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"></path></svg>
                    </span>
                    <span x-show="loading" x-cloak class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity="0.25"></circle><path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path></svg>
                        Iniciando sesión…
                    </span>
                </button>
            </div>
        </form>

        <p class="mt-8 text-center text-sm text-gray-500">
            ¿No tienes una cuenta?
            <a href="{{ route('register') }}" class="font-semibold text-emerald-600 hover:text-emerald-700 hover:underline">
                Regístrate
            </a>
        </p>
    </div>
</x-guest-layout>
