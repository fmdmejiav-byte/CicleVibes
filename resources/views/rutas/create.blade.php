<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-extrabold leading-tight text-gray-900">Registrar nueva ruta</h2>
                <p class="mt-0.5 text-sm text-gray-500">Completa los datos para crear tu recorrido.</p>
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
                <!-- Encabezado del formulario -->
                <div class="flex items-center gap-3 border-b border-gray-100 pb-5">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="19" r="3"></circle><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"></path><circle cx="18" cy="5" r="3"></circle></svg>
                    </span>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Datos de la ruta</h3>
                        <p class="text-sm text-gray-500">Todos los campos marcados con * son obligatorios.</p>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="mt-5 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-3.5 text-sm text-red-700 animate-fade-in">
                        <svg class="mt-0.5 h-5 w-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 8v4M12 16h.01"></path></svg>
                        <p class="font-medium">Revisa los campos marcados para continuar.</p>
                    </div>
                @endif

                <form action="{{ route('rutas.store') }}" method="POST" class="mt-6" @submit="loading = true">
                    @csrf

                    @include('rutas._form')

                    <!-- Acciones -->
                    <div class="mt-8 flex flex-col-reverse gap-3 border-t border-gray-100 pt-6 sm:flex-row sm:items-center sm:justify-end">
                        <a href="{{ route('rutas.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                            Cancelar
                        </a>
                        <button type="submit" :disabled="loading" class="group inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-6 py-2.5 text-sm font-bold text-white shadow-md shadow-emerald-600/25 transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70">
                            <span x-show="!loading" class="inline-flex items-center gap-2">
                                Guardar ruta
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"></path><path d="M17 21v-8H7v8M7 3v5h8"></path></svg>
                            </span>
                            <span x-show="loading" x-cloak class="inline-flex items-center gap-2">
                                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity="0.25"></circle><path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path></svg>
                                GuardandoÔÇª
                            </span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Vista previa del mapa (Leaflet + OpenStreetMap) -->
            <div class="mt-8 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="flex items-center gap-3 border-b border-gray-100 bg-slate-50/70 px-6 py-4">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-100 text-teal-600">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 20l-5.447-2.724A1 1 0 0 1 3 16.382V5.618a1 1 0 0 1 1.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0 0 21 18.382V7.618a1 1 0 0 0-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                    </span>
                    <div>
                        <h3 class="font-bold text-gray-900">Vista previa del mapa</h3>
                        <p class="text-sm text-gray-500">Ubicaci├│n de referencia en Barranquilla.</p>
                    </div>
                </div>
                <div id="ruta-map" class="w-full bg-gray-100" style="height: 420px;"></div>
            </div>

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const element = document.getElementById('ruta-map');
            if (!element || typeof L === 'undefined') return;

            const map = L.map('ruta-map', {
                center: [{{ config('services.map.default_lat') }}, {{ config('services.map.default_lng') }}],
                zoom: {{ config('services.map.default_zoom') }},
            });

            L.tileLayer('{{ config('services.map.tiles_url') }}', {
                maxZoom: {{ config('services.map.tiles_max_zoom') }},
                attribution: '{!! config('services.map.tiles_attribution') !!}',
            }).addTo(map);

            L.marker([{{ config('services.map.default_lat') }}, {{ config('services.map.default_lng') }}])
                .addTo(map)
                .bindPopup('<strong>Barranquilla</strong><br>Zona de referencia para tus rutas.');
        });
    </script>
</x-app-layout>
