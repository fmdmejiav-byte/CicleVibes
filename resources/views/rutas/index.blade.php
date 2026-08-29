<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-extrabold leading-tight text-gray-900">Mis rutas</h2>
                <p class="mt-0.5 text-sm text-gray-500">Tus recorridos personales en CicleVibes.</p>
            </div>
            <a href="{{ route('rutas.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow-md shadow-emerald-600/25 transition hover:bg-emerald-700">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"></path></svg>
                Nueva ruta
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-6 flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700 animate-fade-in">
                    <svg class="mt-0.5 h-5 w-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><path d="m9 11 3 3L22 4"></path></svg>
                    <p class="font-medium">{{ session('success') }}</p>
                </div>
            @endif

            @if($rutas->isEmpty())
                <div class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-gray-200 bg-white py-20 text-center">
                    <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600">
                        <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="19" r="3"></circle><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"></path><circle cx="18" cy="5" r="3"></circle></svg>
                    </span>
                    <h3 class="mt-6 text-lg font-bold text-gray-900">Aún no tienes rutas</h3>
                    <p class="mt-1 max-w-sm text-sm text-gray-500">Crea tu primera ruta para empezar a explorar tu ciudad sobre dos ruedas.</p>
                    <a href="{{ route('rutas.create') }}" class="mt-6 inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-emerald-600/25 transition hover:bg-emerald-700">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"></path></svg>
                        Crear primera ruta
                    </a>
                </div>
            @else
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($rutas as $ruta)
                        <div class="group flex flex-col overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                            <!-- Cabecera -->
                            <div class="flex items-center justify-between bg-gradient-to-r from-emerald-600 to-teal-600 px-5 py-4 text-white">
                                <div class="flex items-center gap-2">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="19" r="3"></circle><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"></path><circle cx="18" cy="5" r="3"></circle></svg>
                                    <span class="max-w-[160px] truncate font-bold">{{ $ruta->nombre }}</span>
                                </div>
                            </div>

                            <!-- Datos -->
                            <div class="flex flex-1 flex-col gap-4 p-5">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </span>
                                    <div>
                                        <p class="text-xs text-gray-400">Origen</p>
                                        <p class="text-sm font-medium text-gray-800">{{ $ruta->origen }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-teal-100 text-teal-600">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                    </span>
                                    <div>
                                        <p class="text-xs text-gray-400">Destino</p>
                                        <p class="text-sm font-medium text-gray-800">{{ $ruta->destino }}</p>
                                    </div>
                                </div>

                                <div class="flex gap-3 rounded-xl bg-gray-50 p-3">
                                    <div class="flex-1">
                                        <p class="text-lg font-extrabold text-gray-900">{{ $ruta->distancia ? $ruta->distancia : '—' }}</p>
                                        <p class="text-xs text-gray-500">km</p>
                                    </div>
                                    <div class="w-px bg-gray-200"></div>
                                    <div class="flex-1">
                                        <p class="text-lg font-extrabold text-gray-900">{{ $ruta->duracion ? $ruta->duracion : '—' }}</p>
                                        <p class="text-xs text-gray-500">min</p>
                                    </div>
                                </div>

                                @if($ruta->descripcion)
                                    <p class="line-clamp-2 text-sm text-gray-500">{{ $ruta->descripcion }}</p>
                                @endif
                            </div>

                            <!-- Acciones -->
                            <div class="flex divide-x divide-gray-100 border-t border-gray-100">
                                <a href="{{ route('rutas.show', $ruta) }}" class="flex flex-1 items-center justify-center gap-1.5 py-3 text-sm font-semibold text-emerald-600 transition hover:bg-emerald-50">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    Ver
                                </a>
                                <a href="{{ route('rutas.edit', $ruta) }}" class="flex flex-1 items-center justify-center gap-1.5 py-3 text-sm font-semibold text-teal-600 transition hover:bg-teal-50">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path></svg>
                                    Editar
                                </a>
                                <form action="{{ route('rutas.destroy', $ruta) }}" method="POST" class="flex flex-1" onsubmit="return confirm('¿Eliminar esta ruta?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="flex flex-1 items-center justify-center gap-1.5 py-3 text-sm font-semibold text-red-600 transition hover:bg-red-50">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path></svg>
                                        Eliminar
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
