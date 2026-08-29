<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'CicleVibes') }} - Pedalea a tu propia vibra</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif

        <style>
            .hero-bg {
                background-image:
                    radial-gradient(circle at 15% 20%, rgba(16,185,129,0.18) 0%, transparent 40%),
                    radial-gradient(circle at 85% 25%, rgba(5,150,105,0.14) 0%, transparent 45%),
                    radial-gradient(circle at 50% 90%, rgba(16,185,129,0.10) 0%, transparent 50%);
            }
            .road-dash {
                background-image: linear-gradient(to right, rgba(255,255,255,0.5) 0%, rgba(255,255,255,0.5) 50%, transparent 50%, transparent 100%);
                background-size: 24px 3px;
                background-repeat: repeat-x;
                background-position: bottom;
            }
        </style>
    </head>
    <body class="font-sans bg-white text-gray-900 antialiased">
        <!-- Navbar -->
        <header class="sticky top-0 z-50 border-b border-emerald-100/60 bg-white/80 backdrop-blur-md">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-5 sm:px-8">
                <a href="/" class="flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-600 text-white">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="18.5" cy="17.5" r="3.5"></circle>
                            <path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"></path>
                        </svg>
                    </span>
                    <span class="text-xl font-extrabold tracking-tight">CicleVibes</span>
                </a>

                <nav class="hidden items-center gap-6 text-sm font-medium text-gray-600 sm:flex">
                    <a href="#caracteristicas" class="hover:text-emerald-600">Características</a>
                    <a href="#como-funciona" class="hover:text-emerald-600">Cómo funciona</a>
                    <a href="#comunidad" class="hover:text-emerald-600">Comunidad</a>
                </nav>

                <div class="flex items-center gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                            Ir al panel
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"></path></svg>
                        </a>
                    @else
                        @if (Route::has('login'))
                            <a href="{{ route('login') }}" class="hidden rounded-lg px-4 py-2 text-sm font-semibold text-gray-700 transition hover:text-emerald-600 sm:inline-flex">
                                Iniciar sesión
                            </a>
                        @endif
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                                Crear cuenta
                            </a>
                        @endif
                    @endauth
                </div>
            </div>
        </header>

        <main>
            <!-- Hero -->
            <section class="hero-bg relative overflow-hidden">
                <div class="mx-auto grid max-w-7xl gap-12 px-5 py-20 sm:px-8 lg:grid-cols-2 lg:items-center lg:py-28">
                    <div class="animate-fade-in-up">
                        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                            <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse-soft"></span>
                            Tu comunidad ciclista local
                        </span>
                        <h1 class="mt-5 text-4xl font-black leading-tight tracking-tight sm:text-5xl">
                            Pedalea por tu ciudad
                            <span class="text-emerald-600">a tu propia vibra.</span>
                        </h1>
                        <p class="mt-5 max-w-xl text-lg text-gray-600">
                            Descubre rutas seguras, registra tu bicicleta y conecta con otros ciclistas de tu barrio. Todo en un solo lugar.
                        </p>
                        <div class="mt-8 flex flex-wrap items-center gap-4">
                            @auth
                                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-emerald-600/25 transition hover:bg-emerald-700">
                                    Explorar mi panel
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"></path></svg>
                                </a>
                            @else
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-emerald-600/25 transition hover:bg-emerald-700">
                                        Empezar gratis
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"></path></svg>
                                    </a>
                                @endif
                                @if (Route::has('login'))
                                    <a href="{{ route('login') }}" class="rounded-xl border border-gray-300 px-6 py-3.5 text-sm font-semibold text-gray-700 transition hover:border-emerald-300 hover:text-emerald-600">
                                        Ya tengo cuenta
                                    </a>
                                @endif
                            @endauth
                        </div>
                        <div class="mt-10 flex items-center gap-8">
                            <div>
                                <p class="text-2xl font-extrabold text-gray-900">34+</p>
                                <p class="text-sm text-gray-500">Rutas locales</p>
                            </div>
                            <div class="h-10 w-px bg-gray-200"></div>
                            <div>
                                <p class="text-2xl font-extrabold text-gray-900">22</p>
                                <p class="text-sm text-gray-500">Bicicletas registradas</p>
                            </div>
                            <div class="h-10 w-px bg-gray-200"></div>
                            <div>
                                <p class="text-2xl font-extrabold text-gray-900">100%</p>
                                <p class="text-sm text-gray-500">Gratis</p>
                            </div>
                        </div>
                    </div>

                    <!-- Hero illustration -->
                    <div class="relative hidden lg:block">
                        <div class="absolute -inset-4 rounded-3xl bg-gradient-to-br from-emerald-200/50 to-teal-200/40 blur-2xl"></div>
                        <div class="relative rounded-3xl bg-gradient-to-br from-emerald-600 to-teal-800 p-10 shadow-2xl shadow-emerald-900/20">
                            <svg class="mx-auto h-72 w-72 text-white/95 animate-float" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="5.5" cy="17.5" r="3.5"/><circle cx="18.5" cy="17.5" r="3.5"/>
                                <path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"/>
                            </svg>
                            <div class="mt-6 space-y-3">
                                <div class="flex items-center gap-3 rounded-xl bg-white/10 p-3 backdrop-blur-sm">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-lime-300/20 text-lime-300">1</span>
                                    <span class="text-sm text-emerald-50">Crea tu cuenta en segundos</span>
                                </div>
                                <div class="flex items-center gap-3 rounded-xl bg-white/10 p-3 backdrop-blur-sm">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-lime-300/20 text-lime-300">2</span>
                                    <span class="text-sm text-emerald-50">Explora y guarda rutas</span>
                                </div>
                                <div class="flex items-center gap-3 rounded-xl bg-white/10 p-3 backdrop-blur-sm">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-lime-300/20 text-lime-300">3</span>
                                    <span class="text-sm text-emerald-50">Registra tu bicicleta</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Features -->
            <section id="caracteristicas" class="border-t border-gray-100 bg-gray-50/60">
                <div class="mx-auto max-w-7xl px-5 py-20 sm:px-8">
                    <div class="max-w-2xl">
                        <span class="text-sm font-bold uppercase tracking-widest text-emerald-600">Características</span>
                        <h2 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Todo lo que necesitas para rodar seguro</h2>
                        <p class="mt-4 text-gray-600">CicleVibes reúne las herramientas esenciales de la movilidad urbana en bicicleta.</p>
                    </div>

                    <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @php
                            $features = [
                                ['Rutas seguras', 'Explora recorridos recomendados por la comunidad para llegar a tu destino.'],
                                ['Registro de bicicletas', 'Lleva un historial de tu bicicleta y sus características.'],
                                ['Reportes de barrio', 'Avisa y conoce incidentes en tu zona para rodar con precaución.'],
                                ['Favoritos', 'Guarda tus rutas preferidas y accede a ellas rápidamente.'],
                                ['Comunidad', 'Comparte publicaciones y comenta con otros ciclistas.'],
                                ['Notificaciones', 'Mantente al tanto de lo que ocurre en tu comunidad.'],
                            ];
                        @endphp
                        @foreach ($features as $feature)
                            <div class="group rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 transition group-hover:bg-emerald-600 group-hover:text-white">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="18.5" cy="17.5" r="3.5"></circle>
                                        <path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"></path>
                                    </svg>
                                </span>
                                <h3 class="mt-4 text-lg font-bold text-gray-900">{{ $feature[0] }}</h3>
                                <p class="mt-1.5 text-sm text-gray-600">{{ $feature[1] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <!-- How it works -->
            <section id="como-funciona" class="bg-white">
                <div class="mx-auto max-w-7xl px-5 py-20 sm:px-8">
                    <div class="max-w-2xl">
                        <span class="text-sm font-bold uppercase tracking-widest text-emerald-600">Cómo funciona</span>
                        <h2 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Empieza en tres simples pasos</h2>
                    </div>

                    <div class="mt-12 grid gap-8 md:grid-cols-3">
                        @php
                            $steps = [
                                [1, 'Crea tu cuenta', 'Regístrate con tu correo y contraseña. Es gratis y no necesita configuración.'],
                                [2, 'Registra tu bici', 'Agrega tu bicicleta con sus datos para tenerla siempre a mano.'],
                                [3, 'Explora y rueda', 'Descubre rutas, guárdalas en favoritos y conecta con la comunidad.'],
                            ];
                        @endphp
                        @foreach ($steps as $step)
                            <div class="relative rounded-2xl border border-gray-100 bg-gray-50/60 p-7">
                                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-600 text-lg font-extrabold text-white shadow-lg shadow-emerald-600/25">{{ $step[0] }}</span>
                                <h3 class="mt-5 text-xl font-bold text-gray-900">{{ $step[1] }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-gray-600">{{ $step[2] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <!-- CTA -->
            <section id="comunidad" class="relative overflow-hidden bg-gradient-to-br from-emerald-600 via-emerald-700 to-teal-800">
                <div class="pointer-events-none absolute -top-20 -right-20 h-72 w-72 rounded-full bg-white/10"></div>
                <div class="pointer-events-none absolute -bottom-24 -left-16 h-80 w-80 rounded-full bg-white/5"></div>
                <div class="road-dash absolute inset-x-0 bottom-0 h-12"></div>
                <div class="relative mx-auto max-w-4xl px-5 py-24 text-center sm:px-8">
                    <h2 class="text-3xl font-black tracking-tight text-white sm:text-4xl">Únete a la comunidad ciclista de CicleVibes</h2>
                    <p class="mx-auto mt-4 max-w-xl text-emerald-50/90">Crea tu cuenta hoy y empieza a descubrir todo lo que tu barrio tiene para ofrecer sobre dos ruedas.</p>
                    <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                        @auth
                            <a href="{{ route('dashboard') }}" class="rounded-xl bg-white px-7 py-3.5 text-sm font-bold text-emerald-700 shadow-lg transition hover:bg-emerald-50">Ir a mi panel</a>
                        @else
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="rounded-xl bg-white px-7 py-3.5 text-sm font-bold text-emerald-700 shadow-lg transition hover:bg-emerald-50">Crear mi cuenta</a>
                            @endif
                            @if (Route::has('login'))
                                <a href="{{ route('login') }}" class="rounded-xl border border-white/40 px-7 py-3.5 text-sm font-bold text-white transition hover:bg-white/10">Iniciar sesión</a>
                            @endif
                        @endauth
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-gray-100 bg-gray-50/60">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-5 py-8 sm:flex-row sm:px-8">
                <div class="flex items-center gap-2">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-600 text-white">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="18.5" cy="17.5" r="3.5"></circle><path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"></path>
                        </svg>
                    </span>
                    <span class="font-bold text-gray-900">CicleVibes</span>
                </div>
                <p class="text-sm text-gray-500">© {{ date('Y') }} CicleVibes. Pedalea a tu propia vibra.</p>
            </div>
        </footer>
    </body>
</html>
