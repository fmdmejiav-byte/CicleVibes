<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Mis bicicletas
            </h2>

            <a href="{{ route('bicicletas.create') }}"
               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                + Registrar bicicleta
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white shadow rounded-lg p-6">

                @if($bicicletas->isEmpty())
                    <p>No tienes bicicletas registradas.</p>
                @else
                    <table class="w-full border">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="p-2">Marca</th>
                                <th class="p-2">Modelo</th>
                                <th class="p-2">Color</th>
                                <th class="p-2">Estado</th>
                                <th class="p-2">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach($bicicletas as $bicicleta)
                                <tr class="border-t">
                                    <td class="p-2">{{ $bicicleta->marca }}</td>
                                    <td class="p-2">{{ $bicicleta->modelo }}</td>
                                    <td class="p-2">{{ $bicicleta->color }}</td>
                                    <td class="p-2">{{ $bicicleta->estado->value }}</td>
                                    <td class="p-2 flex gap-2">
                                        <a
                                            href="{{ route('bicicletas.show', $bicicleta) }}"
                                            class="bg-blue-500 text-white px-3 py-1 rounded">
                                            Ver
                                        </a>
                                        <a
                                            href="{{ route('bicicletas.edit', $bicicleta) }}"
                                            class="bg-yellow-500 text-white px-3 py-1 rounded">
                                            Editar
                                        </a>
                                        <form action="{{ route('bicicletas.destroy', $bicicleta) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    onclick="return confirm('¿Eliminar esta bicicleta?')"
                                                    class="bg-red-500 text-white px-3 py-1 rounded">
                                                Eliminar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

            </div>

        </div>
    </div>
</x-app-layout>