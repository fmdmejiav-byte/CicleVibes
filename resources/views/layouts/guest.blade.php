<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'CicleVibes') }} - @yield('title', 'Autenticación')</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif

        <style>
            .auth-grid-bg {
                background-image:
                    radial-gradient(circle at 20% 20%, rgba(16,185,129,0.12) 0%, transparent 40%),
                    radial-gradient(circle at 80% 10%, rgba(5,150,105,0.10) 0%, transparent 45%),
                    radial-gradient(circle at 15% 85%, rgba(16,185,129,0.08) 0%, transparent 40%),
                    radial-gradient(circle at 85% 80%, rgba(5,150,105,0.10) 0%, transparent 45%);
            }
        </style>
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen auth-grid-bg bg-[#f4f7f6] flex flex-col lg:flex-row">

            <!-- Branding panel -->
            <div class="relative flex flex-col justify-between overflow-hidden bg-gradient-to-br from-emerald-600 via-emerald-700 to-teal-800 text-white lg:w-[46%] lg:min-h-screen px-8 py-10 lg:px-14">
                <!-- decorative circles -->
                <div class="pointer-events-none absolute -top-24 -right-24 h-80 w-80 rounded-full bg-white/10"></div>
                <div class="pointer-events-none absolute -bottom-32 -left-20 h-96 w-96 rounded-full bg-white/5"></div>
                <div class="pointer-events-none absolute top-1/2 right-10 h-4 w-4 rounded-full bg-lime-300/70 animate-float"></div>
                <div class="pointer-events-none absolute bottom-24 left-12 h-3 w-3 rounded-full bg-lime-300/60 animate-float" style="animation-delay:1.2s"></div>

                <div class="relative z-10 flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/15 backdrop-blur-sm ring-1 ring-white/30">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="18.5" cy="17.5" r="3.5"></circle>
                            <path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"></path>
                        </svg>
                    </div>
                    <span class="text-2xl font-bold tracking-tight">CicleVibes</span>
                </div>

                <div class="relative z-10 my-10 lg:my-0">
                    <h1 class="text-3xl lg:text-4xl font-extrabold leading-tight tracking-tight">
                        Pedalea por tu ciudad<br class="hidden lg:block"> a tu propia vibra.
                    </h1>
                    <p class="mt-4 max-w-md text-emerald-50/90 text-base leading-relaxed">
                        Descubre rutas, guarda tus recorridos y conecta con la comunidad ciclista de tu barrio.
                    </p>

                    <ul class="mt-8 space-y-3 text-sm">
                        <li class="flex items-center gap-3">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-lime-300/20 text-lime-300">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                            </span>
                            Explora rutas seguras y recomendadas
                        </li>
                        <li class="flex items-center gap-3">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-lime-300/20 text-lime-300">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                            </span>
                            Registra tu bicicleta y sus datos
                        </li>
                        <li class="flex items-center gap-3">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-lime-300/20 text-lime-300">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                            </span>
                            Reporta incidentes y mantente seguro
                        </li>
                    </ul>
                </div>

                <p class="relative z-10 text-xs text-emerald-100/70">
                    © {{ date('Y') }} CicleVibes. Todos los derechos reservados.
                </p>
            </div>

            <!-- Form panel -->
            <div class="flex flex-1 items-center justify-center px-6 py-10 sm:px-10">
                <div class="w-full max-w-md animate-fade-in-up">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
