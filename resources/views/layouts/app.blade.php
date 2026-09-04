<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'CicleVibes') }} - @yield('title', 'Mapa')</title>

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
    <body class="font-sans bg-[#030a08] text-[#f1fff9] antialiased">
        <div class="hero-bg min-h-screen">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="border-b border-[rgba(0,255,136,0.12)] bg-[#061412]/70 backdrop-blur-md">
                    <div class="mx-auto max-w-7xl px-5 py-6 sm:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>

        <!-- Splash de carga con rueda girando -->
        <div id="app-splash" class="app-splash" aria-hidden="true">
            <div class="flex flex-col items-center gap-5">
                <span class="relative flex h-16 w-16 items-center justify-center text-emerald-400">
                    <svg class="spin-wheel h-16 w-16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <g class="wheel-spokes">
                            <circle cx="12" cy="12" r="7"></circle>
                            <path d="M12 5v14M5 12h14M7 7l10 10M17 7 7 17"></path>
                        </g>
                    </svg>
                </span>
                <span class="text-sm font-bold tracking-wide text-emerald-400">Pedaleando…</span>
            </div>
        </div>
    </body>
</html>