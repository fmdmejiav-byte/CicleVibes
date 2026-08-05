<x-app-layout>

    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Detalle de bicicleta
            </h2>

            <a href="{{ route('bicicletas.index') }}"
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
                        <dt class="font-medium text-gray-600">Marca</dt>
                        <dd>{{ $bicicleta->marca }}</dd>
                    </div>

                    <div class="flex justify-between border-b pb-2">
                        <dt class="font-medium text-gray-600">Modelo</dt>
                        <dd>{{ $bicicleta->modelo }}</dd>
                    </div>

                    <div class="flex justify-between border-b pb-2">
                        <dt class="font-medium text-gray-600">Color</dt>
                        <dd>{{ $bicicleta->color }}</dd>
                    </div>

                    <div class="flex justify-between border-b pb-2">
                        <dt class="font-medium text-gray-600">Número de serie</dt>
                        <dd>{{ $bicicleta->numero_serie }}</dd>
                    </div>

                    <div class="flex justify-between border-b pb-2">
                        <dt class="font-medium text-gray-600">Estado</dt>
                        <dd>{{ $bicicleta->estado->value }}</dd>
                    </div>

                    <div class="flex justify-between border-b pb-2">
                        <dt class="font-medium text-gray-600">Tipo</dt>
                        <dd>{{ $bicicleta->tipoBicicleta->nombre }}</dd>
                    </div>

                    <div class="flex justify-between border-b pb-2">
                        <dt class="font-medium text-gray-600">Barrio</dt>
                        <dd>{{ $bicicleta->barrio?->nombre ?? 'Sin barrio' }}</dd>
                    </div>
                </dl>

                <div class="mt-6 flex gap-2">
                    <a href="{{ route('bicicletas.edit', $bicicleta) }}"
                       class="bg-yellow-500 text-white px-4 py-2 rounded">
                        Editar
                    </a>
                </div>

            </div>

        </div>
    </div>

</x-app-layout>
