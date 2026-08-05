<x-app-layout>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">
            Registrar Ruta
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto">

            <div class="bg-white shadow rounded-lg p-6">

                <form action="{{ route('rutas.store') }}" method="POST">

                    @csrf

                    @include('rutas._form')

                    <div class="mt-6">

                        <button
                            class="bg-blue-600 text-white px-4 py-2 rounded">

                            Guardar Ruta

                        </button>

                    </div>

                </form>

            </div>

            @if(config('services.google_maps.key'))
                <div class="mt-6">

                    <h3 class="font-semibold text-lg text-gray-800 mb-2">
                        Vista previa del mapa
                    </h3>

                    <div
                        id="map"
                        class="w-full bg-gray-200 rounded-lg shadow"
                        style="height: 600px;"></div>

                </div>
            @endif

        </div>
    </div>

    @if(config('services.google_maps.key'))
        <script>
            function initRutaMap() {
                const barranquilla = { lat: 10.9871, lng: -74.7890 };

                new google.maps.Map(document.getElementById('map'), {
                    center: barranquilla,
                    zoom: 12,
                });
            }
        </script>
        <script
            async
            src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&loading=async&callback=initRutaMap&v=weekly"></script>
    @endif

</x-app-layout>
