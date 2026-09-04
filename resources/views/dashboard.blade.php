<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold leading-tight text-gray-900">
            Hola, {{ Auth::user()->nombre }} 👋
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <!-- Bienvenida + CTA principal -->
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-600 via-emerald-700 to-teal-800 p-8 text-white shadow-lg sm:p-10">
                <div class="pointer-events-none absolute -top-16 -right-16 h-56 w-56 rounded-full bg-white/10"></div>
                <div class="pointer-events-none absolute -bottom-20 -left-10 h-64 w-64 rounded-full bg-white/5"></div>
                <div class="relative grid items-center gap-8 lg:grid-cols-[1fr_auto]">
                    <div>
                        <h3 class="text-2xl font-extrabold tracking-tight sm:text-3xl">¡A rodar por Barranquilla!</h3>
                        <p class="mt-2 max-w-lg text-emerald-50/90">
                            Planifica tu próxima ruta en bici, aprovecha las ciclorrutas y navega con turno a turno hasta tu destino.
                        </p>
                        <div class="mt-6 flex flex-wrap items-center gap-4">
                            <a href="{{ route('mapa') }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3.5 text-sm font-bold text-emerald-700 shadow-lg transition hover:bg-emerald-50">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"></path></svg>
                                Planificar una ruta
                            </a>
                            <a href="{{ route('mapa') }}" class="inline-flex items-center gap-2 rounded-xl border border-white/40 px-6 py-3.5 text-sm font-bold text-white transition hover:bg-white/10">
                                Explorar ciclorrutas
                            </a>
                        </div>
                    </div>
                    <div class="hidden lg:block">
                        <span class="flex h-40 w-40 items-center justify-center rounded-3xl bg-white/10 text-emerald-100 ring-1 ring-white/20">
                            <svg class="h-24 w-24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="18.5" cy="17.5" r="3.5"></circle>
                                <path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"></path>
                            </svg>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Accesos rápidos -->
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <a href="{{ route('mapa') }}" class="group rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 transition group-hover:bg-emerald-600 group-hover:text-white">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 20l-5.5-2.5a1 1 0 0 1-.5-.9V5a1 1 0 0 1 1.4-.9L9 6l6-2.5 5.5 2.5a1 1 0 0 1 .5.9v11.6a1 1 0 0 1-1.4.9L15 18l-6 2z"></path><path d="M9 5v15M15 3v15"></path></svg>
                    </span>
                    <h4 class="mt-4 text-lg font-bold text-gray-900">Navegación en mapa</h4>
                    <p class="mt-1 text-sm text-gray-600">Calcula rutas para bici con perfil ciclista y sigue las indicaciones paso a paso.</p>
                    <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-emerald-600">
                        Abrir el mapa
                        <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"></path></svg>
                    </span>
                </a>

                <a href="{{ route('mapa') }}" class="group rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-teal-100 text-teal-600 transition group-hover:bg-teal-600 group-hover:text-white">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="18.5" cy="17.5" r="3.5"></circle><path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"></path></svg>
                    </span>
                    <h4 class="mt-4 text-lg font-bold text-gray-900">Red de ciclorrutas</h4>
                    <p class="mt-1 text-sm text-gray-600">Visualiza la infraestructura ciclista de la ciudad y prioriza tus recorridos por ellas.</p>
                    <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-teal-600">
                        Ver capa de ciclorrutas
                        <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"></path></svg>
                    </span>
                </a>

                <a href="{{ route('profile.edit') }}" class="group rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-100 text-amber-600 transition group-hover:bg-amber-500 group-hover:text-white">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"></circle><path d="M4 21c0-4 3.6-6 8-6s8 2 8 6"></path></svg>
                    </span>
                    <h4 class="mt-4 text-lg font-bold text-gray-900">Tu perfil</h4>
                    <p class="mt-1 text-sm text-gray-600">Mantén tus datos al día para una mejor experiencia de navegación.</p>
                    <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-amber-600">
                        Editar perfil
                        <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"></path></svg>
                    </span>
                </a>
            </div>
        </div>
    </div>
</x-app-layout>