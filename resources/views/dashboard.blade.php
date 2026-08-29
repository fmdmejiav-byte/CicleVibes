<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold leading-tight text-gray-900">
            Hola, {{ Auth::user()->nombre }} 👋
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <!-- Bienvenida -->
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-600 via-emerald-700 to-teal-800 p-8 text-white shadow-lg">
                <div class="pointer-events-none absolute -top-16 -right-16 h-56 w-56 rounded-full bg-white/10"></div>
                <div class="pointer-events-none absolute -bottom-20 -left-10 h-64 w-64 rounded-full bg-white/5"></div>
                <div class="relative">
                    <h3 class="text-2xl font-extrabold tracking-tight">Bienvenido a CicleVibes</h3>
                    <p class="mt-2 max-w-lg text-emerald-50/90">Estás autenticado correctamente. Explora tus rutas y gestiona tu bicicleta desde aquí.</p>
                </div>
            </div>

            <!-- Accesos rápidos -->
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-2">
                <a href="{{ route('bicicletas.index') }}" class="group rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 transition group-hover:bg-emerald-600 group-hover:text-white">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="18.5" cy="17.5" r="3.5"></circle>
                            <path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"></path>
                        </svg>
                    </span>
                    <h4 class="mt-4 text-lg font-bold text-gray-900">Mis bicicletas</h4>
                    <p class="mt-1 text-sm text-gray-600">Gestiona el registro de tus bicicletas.</p>
                    <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-emerald-600">
                        Ir a bicicletas
                        <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"></path></svg>
                    </span>
                </a>

                <a href="{{ route('rutas.index') }}" class="group rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-teal-100 text-teal-600 transition group-hover:bg-teal-600 group-hover:text-white">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="19" r="3"></circle><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"></path><circle cx="18" cy="5" r="3"></circle></svg>
                    </span>
                    <h4 class="mt-4 text-lg font-bold text-gray-900">Mis rutas</h4>
                    <p class="mt-1 text-sm text-gray-600">Crea y gestiona tus recorridos.</p>
                    <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-teal-600">
                        Ir a rutas
                        <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"></path></svg>
                    </span>
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
