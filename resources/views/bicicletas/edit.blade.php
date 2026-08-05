<x-app-layout>

    <x-slot name="header">
        <h2 class="font-semibold text-xl">
            Editar Bicicleta
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto">

            <div class="bg-white p-6 rounded shadow">

                <form
                    action="{{ route('bicicletas.update', $bicicleta) }}"
                    method="POST">

                    @csrf
                    @method('PUT')

                    @include('bicicletas._form')

                    <button
                        class="mt-4 bg-yellow-500 text-white px-4 py-2 rounded">

                        Actualizar

                    </button>

                </form>

            </div>

        </div>
    </div>

</x-app-layout>