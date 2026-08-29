<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-extrabold leading-tight text-gray-900">Detalle de la ruta</h2>
                <p class="mt-0.5 text-sm text-gray-500">{{ $ruta->nombre }}</p>
            </div>
            <a href="{{ route('rutas.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:border-emerald-300 hover:text-emerald-600">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"></path></svg>
                Mis rutas
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">

            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <!-- Cabecera -->
                <div class="relative bg-gradient-to-r from-emerald-600 to-teal-600 px-8 py-8 text-white">
                    <div class="pointer-events-none absolute -top-10 -right-10 h-40 w-40 rounded-full bg-white/10"></div>
                    <div class="pointer-events-none absolute -bottom-12 -left-8 h-44 w-44 rounded-full bg-white/5"></div>
                    <div class="relative flex items-center gap-4">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur-sm ring-1 ring-white/30">
                            <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="19" r="3"></circle><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"></path><circle cx="18" cy="5" r="3"></circle></svg>
                        </span>
                        <div>
                            <h3 class="text-2xl font-extrabold tracking-tight">{{ $ruta->nombre }}</h3>
                            <p class="mt-0.5 text-emerald-50/90">Ruta de la comunidad</p>
                        </div>
                    </div>
                </div>

                <div class="p-8">
                    <!-- Estadísticas -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-5">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Distancia</p>
                            <p class="mt-1 text-2xl font-extrabold text-gray-900">{{ $ruta->distancia ? $ruta->distancia.' km' : '—' }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-5">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Duración estimada</p>
                            <p class="mt-1 text-2xl font-extrabold text-gray-900">{{ $ruta->duracion ? $ruta->duracion.' min' : '—' }}</p>
                        </div>
                    </div>

                    <!-- Descripción -->
                    <div class="mt-8">
                        <h4 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-gray-500">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path></svg>
                            Descripción
                        </h4>
                        <p class="mt-2 leading-relaxed text-gray-700">{{ $ruta->descripcion ?? 'Sin descripción.' }}</p>
                    </div>

                    <!-- Origen y destino -->
                    <div class="mt-8 grid gap-4 sm:grid-cols-2">
                        <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4">
                            <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </span>
                            <div>
                                <p class="text-xs text-gray-400">Origen</p>
                                <p class="font-semibold text-gray-800">{{ $ruta->origen }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4">
                            <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-teal-100 text-teal-600">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            </span>
                            <div>
                                <p class="text-xs text-gray-400">Destino</p>
                                <p class="font-semibold text-gray-800">{{ $ruta->destino }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Acciones -->
                    <div class="mt-8 flex flex-wrap gap-3 border-t border-gray-100 pt-6">
                        <a href="{{ route('rutas.edit', $ruta) }}" class="inline-flex items-center gap-2 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-teal-600/25 transition hover:bg-teal-700">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path></svg>
                            Editar
                        </a>
                        <form action="{{ route('rutas.destroy', $ruta) }}" method="POST" onsubmit="return confirm('¿Eliminar esta ruta?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-red-50 px-5 py-2.5 text-sm font-bold text-red-600 transition hover:bg-red-100">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path></svg>
                                Eliminar
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Mapa (Leaflet + OpenStreetMap) -->
            <div class="mt-8 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                    <div class="flex items-center gap-3 border-b border-gray-100 bg-slate-50/70 px-6 py-4">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-100 text-teal-600">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 20l-5.447-2.724A1 1 0 0 1 3 16.382V5.618a1 1 0 0 1 1.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0 0 21 18.382V7.618a1 1 0 0 0-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                        </span>
                        <div>
                            <h3 class="font-bold text-gray-900">Mapa de la ruta</h3>
                            <p class="text-sm text-gray-500">Zona de referencia de la ruta.</p>
                        </div>
                    </div>
                    <div id="ruta-map" class="w-full bg-gray-100" style="height: 400px;"></div>
                </div>

            @if(config('services.google_maps.key') && $ruta->polilinea)
                <div class="mt-8 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                    <div class="flex items-center gap-3 border-b border-gray-100 bg-slate-50/70 px-6 py-4">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-100 text-blue-600">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 20l-5.447-2.724A1 1 0 0 1 3 16.382V5.618a1 1 0 0 1 1.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0 0 21 18.382V7.618a1 1 0 0 0-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                        </span>
                        <div>
                            <h3 class="font-bold text-gray-900">Ruta en el mapa</h3>
                            <p class="text-sm text-gray-500">Recorrido calculado sobre el mapa.</p>
                        </div>
                    </div>
                    <div id="map-ruta" class="w-full bg-gray-200" style="height: 480px;"></div>
                </div>

                <script>
                    window.initRutaShowMap = function () {
                        const map = new google.maps.Map(document.getElementById('map-ruta'), {
                            center: { lat: 10.9871, lng: -74.7890 },
                            zoom: 12,
                            mapTypeId: 'roadmap',
                        });

                        const coordenadas = @json($coordenadasRuta);

                        if (!coordenadas || coordenadas.length < 2) {
                            return;
                        }

                        new google.maps.Polyline({
                            path: coordenadas,
                            map: map,
                            strokeColor: '#2563eb',
                            strokeWeight: 6,
                            strokeOpacity: 0.9,
                        });

                        new google.maps.Marker({ position: coordenadas[0], map: map, label: 'A' });
                        new google.maps.Marker({ position: coordenadas[coordenadas.length - 1], map: map, label: 'B' });

                        const bounds = new google.maps.LatLngBounds();
                        coordenadas.forEach((punto) => bounds.extend(punto));
                        map.fitBounds(bounds);
                    };
                </script>
                <script
                    async
                    src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&loading=async&callback=initRutaShowMap&v=weekly"></script>
            @endif
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
        });
    </script>
</x-app-layout>
