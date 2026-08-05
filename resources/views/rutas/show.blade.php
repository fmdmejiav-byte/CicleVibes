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

        </div>
    </div>

</x-app-layout>
