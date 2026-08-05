<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Mis rutas
            </h2>

            <a href="{{ route('rutas.create') }}"
               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                + Registrar ruta
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white shadow rounded-lg p-6">

                @if($rutas->isEmpty())
                    <p>No tienes rutas registradas.</p>
                @else
                    <table class="w-full border">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="p-2">Nombre</th>
                                <th class="p-2">Origen</th>
                                <th class="p-2">Destino</th>
                                <th class="p-2">Distancia</th>
                                <th class="p-2">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach($rutas as $ruta)
                                <tr class="border-t">
                                    <td class="p-2">{{ $ruta->nombre }}</td>
                                    <td class="p-2">{{ $ruta->origen }}</td>
                                    <td class="p-2">{{ $ruta->destino }}</td>
                                    <td class="p-2">{{ $ruta->distancia ? $ruta->distancia.' km' : '—' }}</td>
                                    <td class="p-2 flex gap-2">
                                        <a
                                            href="{{ route('rutas.show', $ruta) }}"
                                            class="bg-blue-500 text-white px-3 py-1 rounded">
                                            Ver
                                        </a>
                                        <a
                                            href="{{ route('rutas.edit', $ruta) }}"
                                            class="bg-yellow-500 text-white px-3 py-1 rounded">
                                            Editar
                                        </a>
                                        <form action="{{ route('rutas.destroy', $ruta) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    onclick="return confirm('¿Eliminar esta ruta?')"
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
