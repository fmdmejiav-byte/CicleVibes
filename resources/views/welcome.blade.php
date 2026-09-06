<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'CicleVibes') }} - Pedalea a tu propia vibra</title>

        <meta name="theme-color" content="#030a08">
        <meta name="description" content="CicleVibes: planifica y navega rutas en bicicleta por Barranquilla con perfil ciclista, ciclorrutas y navegación paso a paso.">

        <!-- Favicons -->
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900&display=swap" rel="stylesheet" />

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="font-sans bg-[#030a08] text-[#f1fff9] antialiased">
        <!-- Navbar -->
        <header class="sticky top-0 z-50 border-b border-[rgba(0,255,136,0.14)] bg-[#061412]/72 backdrop-blur-xl">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-5 sm:px-8">
                <a href="/" class="flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[linear-gradient(135deg,#00ff88,#00b3a0)] text-[#02140d] shadow-[0_0_18px_rgba(0,255,136,0.35)]">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="18.5" cy="17.5" r="3.5"></circle>
                            <path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"></path>
                        </svg>
                    </span>
                    <span class="text-xl font-extrabold tracking-tight text-[#f1fff9]">
                        Cicle<span class="text-[#00ff88] drop-shadow-[0_0_10px_rgba(0,255,136,0.5)]">Vibes</span>
                    </span>
                </a>

                <nav class="hidden items-center gap-6 text-sm font-semibold text-[#a7b8b2] sm:flex">
                    <a href="#caracteristicas" class="transition hover:text-[#00ff88]">Características</a>
                    <a href="#como-funciona" class="transition hover:text-[#00ff88]">Cómo funciona</a>
                    <a href="#comunidad" class="transition hover:text-[#00ff88]">Comunidad</a>
                </nav>

                <div class="flex items-center gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="cv-neon-button !px-5 !py-2.5 !text-sm">
                            Ir al panel
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"></path></svg>
                        </a>
                    @else
                        @if (Route::has('login'))
                            <a href="{{ route('login') }}" class="hidden rounded-lg px-4 py-2 text-sm font-semibold text-[#a7b8b2] transition hover:text-[#00ff88] sm:inline-flex">
                                Iniciar sesión
                            </a>
                        @endif
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="cv-neon-button !px-5 !py-2.5 !text-sm">
                                Crear cuenta
                            </a>
                        @endif
                    @endauth
                </div>
            </div>
        </header>

        <main>
            <!-- Hero -->
            <section class="cv-bg relative overflow-hidden">
                <div class="pointer-events-none absolute -top-24 -left-24 h-80 w-80 rounded-full bg-[#00ff88]/10 blur-3xl"></div>
                <div class="pointer-events-none absolute -bottom-28 -right-16 h-96 w-96 rounded-full bg-[#00e5ff]/10 blur-3xl"></div>

                <div class="mx-auto grid max-w-7xl gap-12 px-5 py-20 sm:px-8 lg:grid-cols-2 lg:items-center lg:py-28">
                    <div class="animate-fade-in-up">
                        <span class="cv-badge">
                            <span class="h-2 w-2 rounded-full bg-[#00ff88] animate-pulse-soft"></span>
                            Tu comunidad ciclista local
                        </span>
                        <h1 class="mt-5 text-4xl font-black leading-tight tracking-tight text-[#f1fff9] sm:text-5xl">
                            Pedalea por tu ciudad
                            <span class="cv-neon-green cv-text-glow">a tu propia vibra.</span>
                        </h1>
                        <p class="mt-5 max-w-xl text-lg leading-relaxed text-[#a7b8b2]">
                            Rutas para bicicleta, ciclorrutas priorizadas y navegación paso a paso en Barranquilla. Todo en un solo lugar.
                        </p>
                        <div class="mt-8 flex flex-wrap items-center gap-4">
                            @auth
                                <a href="{{ route('dashboard') }}" class="cv-neon-button">
                                    Explorar mi panel
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"></path></svg>
                                </a>
                            @else
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="cv-neon-button">
                                        Empezar gratis
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"></path></svg>
                                    </a>
                                @endif
                                @if (Route::has('login'))
                                    <a href="{{ route('login') }}" class="cv-neon-ghost">
                                        Ya tengo cuenta
                                    </a>
                                @endif
                            @endauth
                        </div>
                        <div class="mt-10 flex items-center gap-8">
                            <div>
                                <p class="text-2xl font-extrabold text-[#00ff88]">34+</p>
                                <p class="text-sm text-[#a7b8b2]">Rutas locales</p>
                            </div>
                            <div class="h-10 w-px bg-[rgba(0,255,136,0.15)]"></div>
                            <div>
                                <p class="text-2xl font-extrabold text-[#00e5ff]">40+ km</p>
                                <p class="text-sm text-[#a7b8b2]">de ciclorrutas</p>
                            </div>
                            <div class="h-10 w-px bg-[rgba(0,255,136,0.15)]"></div>
                            <div>
                                <p class="text-2xl font-extrabold text-[#f1fff9]">100%</p>
                                <p class="text-sm text-[#a7b8b2]">Gratis</p>
                            </div>
                        </div>
                    </div>

                    <!-- Hero illustration -->
                    <div class="relative hidden lg:block">
                        <div class="absolute -inset-4 rounded-3xl bg-[rgba(0,255,136,0.08)] blur-2xl"></div>
                        <div class="cv-card relative p-10 shadow-[0_20px_50px_rgba(0,0,0,0.5)]">
                            <!-- ruta de fondo -->
                            <svg class="pointer-events-none absolute inset-x-10 top-0 h-full w-[calc(100%-5rem)] text-[#00e5ff]/20" viewBox="0 0 200 260" fill="none" preserveAspectRatio="none" aria-hidden="true">
                                <path d="M20 240 C 60 200, 40 150, 100 130 S 170 60, 180 20" stroke="currentColor" stroke-width="2.5" stroke-dasharray="3 8" stroke-linecap="round" />
                            </svg>
                            <div class="relative flex flex-col items-center">
                                <span class="flex h-20 w-20 items-center justify-center rounded-full border border-[rgba(0,229,255,0.35)] bg-[rgba(0,229,255,0.1)] text-[#00e5ff] shadow-[0_0_24px_rgba(0,229,255,0.25)] animate-float">
                                    <svg class="h-10 w-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="18.5" cy="17.5" r="3.5"></circle>
                                        <path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"></path>
                                    </svg>
                                </span>

                                <div class="mt-8 w-full space-y-3">
                                    <div class="flex items-center gap-3 rounded-xl border border-[rgba(0,255,136,0.18)] bg-[rgba(9,24,20,0.7)] p-3 backdrop-blur-sm">
                                        <span class="cv-step-num">1</span>
                                        <span class="text-sm font-semibold text-[#f1fff9]">Explora rutas para bicicleta</span>
                                    </div>
                                    <div class="flex items-center gap-3 rounded-xl border border-[rgba(0,255,136,0.18)] bg-[rgba(9,24,20,0.7)] p-3 backdrop-blur-sm">
                                        <span class="cv-step-num">2</span>
                                        <span class="text-sm font-semibold text-[#f1fff9]">Prioriza ciclorrutas en tu trayecto</span>
                                    </div>
                                    <div class="flex items-center gap-3 rounded-xl border border-[rgba(0,255,136,0.18)] bg-[rgba(9,24,20,0.7)] p-3 backdrop-blur-sm">
                                        <span class="cv-step-num">3</span>
                                        <span class="text-sm font-semibold text-[#f1fff9]">Navega con indicaciones paso a paso</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Features -->
            <section id="caracteristicas" class="border-t border-[rgba(0,255,136,0.12)] bg-[#061412]">
                <div class="mx-auto max-w-7xl px-5 py-20 sm:px-8">
                    <div class="max-w-2xl">
                        <span class="text-sm font-bold uppercase tracking-widest text-[#00ff88]">Características</span>
                        <h2 class="mt-3 text-3xl font-black tracking-tight text-[#f1fff9] sm:text-4xl">Todo lo que necesitas para rodar seguro</h2>
                        <p class="mt-4 text-[#a7b8b2]">CicleVibes reúne las herramientas esenciales de la movilidad urbana en bicicleta.</p>
                    </div>

                    <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @php
                            $features = [
                                ['Rutas seguras', 'Recorridos calculados para bicicleta, con perfil ciclista y tiempos realistas.'],
                                ['Ciclorrutas priorizadas', 'Activa el modo ciclorrutas y elige el trayecto que más las aproveche.'],
                                ['Navegación paso a paso', 'Indicaciones claras mientras ruedas, con recálculo automático si te desvías.'],
                                ['Búsqueda de destinos', 'Encuentra cualquier lugar de Barranquilla y planifica al instante.'],
                                ['Ubicación actual', 'Fija tu posición real con geolocalización y úsala como origen de tus rutas.'],
                                ['Gratis', 'Sin costos ocultos: todo lo esencial para moverte sobre dos ruedas.'],
                            ];
                        @endphp
                        @foreach ($features as $feature)
                            <div class="cv-card cv-card-hover group p-6">
                                <span class="flex h-11 w-11 items-center justify-center rounded-xl border border-[rgba(0,255,136,0.25)] bg-[rgba(0,255,136,0.1)] text-[#00ff88] transition group-hover:shadow-[0_0_18px_rgba(0,255,136,0.4)]">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="18.5" cy="17.5" r="3.5"></circle>
                                        <path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"></path>
                                    </svg>
                                </span>
                                <h3 class="mt-4 text-lg font-bold text-[#f1fff9]">{{ $feature[0] }}</h3>
                                <p class="mt-1.5 text-sm text-[#a7b8b2]">{{ $feature[1] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <!-- How it works -->
            <section id="como-funciona" class="bg-[#030a08]">
                <div class="mx-auto max-w-7xl px-5 py-20 sm:px-8">
                    <div class="max-w-2xl">
                        <span class="text-sm font-bold uppercase tracking-widest text-[#00e5ff]">Cómo funciona</span>
                        <h2 class="mt-3 text-3xl font-black tracking-tight text-[#f1fff9] sm:text-4xl">Empieza en tres simples pasos</h2>
                    </div>

                    <div class="mt-12 grid gap-8 md:grid-cols-3">
                        @php
                            $steps = [
                                [1, 'Crea tu cuenta', 'Regístrate con tu correo y contraseña. Es gratis y no necesita configuración.'],
                                [2, 'Define tu recorrido', 'Señala origen y destino, y prioriza ciclorrutas si lo deseas.'],
                                [3, 'Explora y rueda', 'Elige tu alternativa y navega con indicaciones paso a paso.'],
                            ];
                        @endphp
                        @foreach ($steps as $step)
                            <div class="cv-card relative p-7">
                                <span class="cv-step-num !h-12 !w-12 !text-lg">{{ $step[0] }}</span>
                                <h3 class="mt-5 text-xl font-bold text-[#f1fff9]">{{ $step[1] }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-[#a7b8b2]">{{ $step[2] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <!-- CTA -->
            <section id="comunidad" class="relative overflow-hidden border-t border-[rgba(0,255,136,0.12)] bg-[linear-gradient(120deg,#04311f,#0b1e1a_60%,#03101b)]">
                <div class="pointer-events-none absolute -top-20 -right-20 h-72 w-72 rounded-full bg-[#00ff88]/10 blur-3xl"></div>
                <div class="pointer-events-none absolute -bottom-24 -left-16 h-80 w-80 rounded-full bg-[#00e5ff]/10 blur-3xl"></div>
                <div class="absolute inset-x-0 bottom-0 h-px bg-[rgba(0,255,136,0.3)]"></div>
                <div class="relative mx-auto max-w-4xl px-5 py-24 text-center sm:px-8">
                    <h2 class="text-3xl font-black tracking-tight text-[#f1fff9] sm:text-4xl">Únete a la comunidad ciclista de CicleVibes</h2>
                    <p class="mx-auto mt-4 max-w-xl text-[#a7b8b2]">Crea tu cuenta hoy y empieza a descubrir todo lo que Barranquilla tiene para ofrecer sobre dos ruedas.</p>
                    <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                        @auth
                            <a href="{{ route('dashboard') }}" class="cv-neon-button">Ir a mi panel</a>
                        @else
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="cv-neon-button">Crear mi cuenta</a>
                            @endif
                            @if (Route::has('login'))
                                <a href="{{ route('login') }}" class="cv-neon-ghost">Iniciar sesión</a>
                            @endif
                        @endauth
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-[rgba(0,255,136,0.12)] bg-[#061412]">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-5 py-8 sm:flex-row sm:px-8">
                <div class="flex items-center gap-2">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[linear-gradient(135deg,#00ff88,#00b3a0)] text-[#02140d]">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="18.5" cy="17.5" r="3.5"></circle><path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"></path>
                        </svg>
                    </span>
                    <span class="font-extrabold text-[#f1fff9]">CicleVibes</span>
                </div>
                <p class="text-sm text-[#6f817a]">© {{ date('Y') }} CicleVibes. Pedalea a tu propia vibra.</p>
            </div>
        </footer>
    </body>
</html>