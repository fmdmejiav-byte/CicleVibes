<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'CicleVibes') }} - @yield('title', 'Autenticación')</title>

        <meta name="theme-color" content="#030a08">
        <meta name="description" content="CicleVibes: planifica y navega rutas en bicicleta por Barranquilla con perfil ciclista, ciclorrutas y navegación paso a paso.">

        <!-- Favicons -->
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="font-sans antialiased">
        <div class="cv-bg min-h-screen flex flex-col lg:flex-row">

            <!-- Branding panel -->
            <div class="relative flex flex-col justify-between overflow-hidden border-b border-[rgba(0,255,136,0.12)] bg-[linear-gradient(160deg,#061412,#0b1e1a_55%,#030a08)] px-8 py-10 lg:w-[46%] lg:min-h-screen lg:border-b-0 lg:border-r lg:px-14 text-[#f1fff9]">
                <!-- decorative glows -->
                <div class="pointer-events-none absolute -top-24 -right-24 h-80 w-80 rounded-full bg-[#00ff88]/10 blur-3xl"></div>
                <div class="pointer-events-none absolute -bottom-32 -left-20 h-96 w-96 rounded-full bg-[#00e5ff]/10 blur-3xl"></div>
                <div class="pointer-events-none absolute top-1/2 right-10 h-3 w-3 rounded-full bg-[#00ff88]/70 shadow-[0_0_12px_rgba(0,255,136,0.8)] animate-float"></div>
                <div class="pointer-events-none absolute bottom-24 left-12 h-3 w-3 rounded-full bg-[#00e5ff]/60 shadow-[0_0_12px_rgba(0,229,255,0.7)] animate-float" style="animation-delay:1.2s"></div>

                <div class="relative z-10 flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-[linear-gradient(135deg,#00ff88,#00b3a0)] text-[#02140d] shadow-[0_0_18px_rgba(0,255,136,0.35)]">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="18.5" cy="17.5" r="3.5"></circle>
                            <path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"></path>
                        </svg>
                    </div>
                    <span class="text-2xl font-extrabold tracking-tight text-[#f1fff9]">
                        Cicle<span class="text-[#00ff88] drop-shadow-[0_0_10px_rgba(0,255,136,0.5)]">Vibes</span>
                    </span>
                </div>

                <div class="relative z-10 my-10 lg:my-0">
                    <span class="cv-badge">
                        <span class="h-2 w-2 rounded-full bg-[#00ff88] animate-pulse-soft" aria-hidden="true"></span>
                        Tu comunidad ciclista local
                    </span>
                    <h1 class="mt-5 text-3xl lg:text-4xl font-extrabold leading-tight tracking-tight text-[#f1fff9]">
                        Pedalea por tu ciudad<br class="hidden lg:block"> a tu propia vibra.
                    </h1>
                    <p class="mt-4 max-w-md text-base leading-relaxed text-[#a7b8b2]">
                        Descubre rutas, prioriza ciclorrutas y muévete por Barranquilla de forma más inteligente.
                    </p>

                    <ul class="mt-8 space-y-3 text-sm text-[#a7b8b2]">
                        <li class="flex items-center gap-3">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full border border-[rgba(0,255,136,0.3)] bg-[rgba(0,255,136,0.1)] text-[#00ff88]">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"></path></svg>
                            </span>
                            Explora rutas seguras para bicicleta
                        </li>
                        <li class="flex items-center gap-3">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full border border-[rgba(0,229,255,0.3)] bg-[rgba(0,229,255,0.1)] text-[#00e5ff]">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"></path></svg>
                            </span>
                            Navega con indicaciones paso a paso
                        </li>
                        <li class="flex items-center gap-3">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full border border-[rgba(255,159,67,0.3)] bg-[rgba(255,159,67,0.1)] text-[#ff9f43]">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"></path></svg>
                            </span>
                            Prioriza las ciclorrutas de la ciudad
                        </li>
                    </ul>
                </div>

                <p class="relative z-10 text-xs text-[#6f817a]">
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