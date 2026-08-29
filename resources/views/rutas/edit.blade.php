<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-extrabold leading-tight text-gray-900">Editar ruta</h2>
                <p class="mt-0.5 text-sm text-gray-500">Actualiza los datos de tu recorrido.</p>
            </div>
            <a href="{{ route('rutas.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:border-emerald-300 hover:text-emerald-600">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"></path></svg>
                Mis rutas
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">

            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm sm:p-8" x-data="{ loading: false }">
                <div class="flex items-center gap-3 border-b border-gray-100 pb-5">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-teal-100 text-teal-600">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path></svg>
                    </span>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Editar: {{ $ruta->nombre }}</h3>
                        <p class="text-sm text-gray-500">Los campos marcados con * son obligatorios.</p>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="mt-5 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-3.5 text-sm text-red-700 animate-fade-in">
                        <svg class="mt-0.5 h-5 w-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 8v4M12 16h.01"></path></svg>
                        <p class="font-medium">Revisa los campos marcados para continuar.</p>
                    </div>
                @endif

                <form action="{{ route('rutas.update', $ruta) }}" method="POST" class="mt-6" @submit="loading = true">
                    @csrf
                    @method('PUT')

                    @include('rutas._form')

                    <div class="mt-8 flex flex-col-reverse gap-3 border-t border-gray-100 pt-6 sm:flex-row sm:items-center sm:justify-end">
                        <a href="{{ route('rutas.show', $ruta) }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                            Cancelar
                        </a>
                        <button type="submit" :disabled="loading" class="group inline-flex items-center justify-center gap-2 rounded-lg bg-teal-600 px-6 py-2.5 text-sm font-bold text-white shadow-md shadow-teal-600/25 transition hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70">
                            <span x-show="!loading" class="inline-flex items-center gap-2">
                                Actualizar ruta
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                            </span>
                            <span x-show="loading" x-cloak class="inline-flex items-center gap-2">
                                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity="0.25"></circle><path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path></svg>
                                Actualizando…
                            </span>
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
