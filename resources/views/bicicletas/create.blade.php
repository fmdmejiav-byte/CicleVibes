<x-app-layout>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">
            Registrar Bicicleta
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto">

            <div class="bg-white shadow rounded-lg p-6">

                <form action="{{ route('bicicletas.store') }}" method="POST">

                    @csrf

                    @include('bicicletas._form')

                    <div class="mt-6">

                        <button
                            class="bg-blue-600 text-white px-4 py-2 rounded">

                            Guardar Bicicleta

                        </button>

                    </div>

                </form>

            </div>

        </div>
    </div>

</x-app-layout>