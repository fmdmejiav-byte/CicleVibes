<x-app-layout>

    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Detalle de ruta
            </h2>

            <a href="{{ route('rutas.index') }}"
               class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                Volver
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto">

            <div class="bg-white shadow rounded-lg p-6">

                <dl class="space-y-4">
                    <div class="flex justify-between border-b pb-2">
                        <dt class="font-medium text-gray-600">Nombre</dt>
                        <dd>{{ $ruta->nombre }}</dd>
                    </div>

                    <div class="flex justify-between border-b pb-2">
                        <dt class="font-medium text-gray-600">Descripción</dt>
                        <dd>{{ $ruta->descripcion ?? 'Sin descripción' }}</dd>
                    </div>

                    <div class="flex justify-between border-b pb-2">
                        <dt class="font-medium text-gray-600">Origen</dt>
                        <dd>{{ $ruta->origen }}</dd>
                    </div>

                    <div class="flex justify-between border-b pb-2">
                        <dt class="font-medium text-gray-600">Destino</dt>
                        <dd>{{ $ruta->destino }}</dd>
                    </div>

                    <div class="flex justify-between border-b pb-2">
                        <dt class="font-medium text-gray-600">Distancia</dt>
                        <dd>{{ $ruta->distancia ? $ruta->distancia.' km' : '—' }}</dd>
                    </div>

                    <div class="flex justify-between border-b pb-2">
                        <dt class="font-medium text-gray-600">Duración</dt>
                        <dd>{{ $ruta->duracion ? $ruta->duracion.' min' : '—' }}</dd>
                    </div>
                </dl>

                <div class="mt-6 flex gap-2">
                    <a href="{{ route('rutas.edit', $ruta) }}"
                       class="bg-yellow-500 text-white px-4 py-2 rounded">
                        Editar
                    </a>
                </div>

            </div>

            @if(config('services.google_maps.key') && $ruta->polilinea)
                <div class="bg-white shadow rounded-lg p-6 mt-6">

                    <h3 class="font-semibold text-lg text-gray-800 mb-3">
                        Ruta en el mapa
                    </h3>

                    <div
                        id="map-ruta"
                        class="w-full bg-gray-200 rounded-lg shadow"
                        style="height: 480px;"></div>

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

</x-app-layout>
