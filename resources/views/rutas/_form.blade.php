<div class="grid gap-5 sm:grid-cols-2">

    <!-- Nombre -->
    <div class="sm:col-span-2">
        <label for="nombre" class="block text-sm font-semibold text-gray-700">Nombre de la ruta <span class="text-red-500">*</span></label>
        <div class="relative mt-1.5">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="18.5" cy="17.5" r="3.5"></circle><path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"></path></svg>
            </span>
            <input
                type="text"
                id="nombre"
                name="nombre"
                value="{{ old('nombre', $ruta->nombre ?? '') }}"
                placeholder="Ej. Ruta del r├¡o, ida al trabajoÔÇª"
                class="block w-full rounded-lg border-gray-300 bg-gray-50 pl-11 pr-4 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm transition focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/30 @error('nombre') border-red-300 @enderror"
            >
        </div>
        @error('nombre')
            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <!-- Descripci├│n -->
    <div class="sm:col-span-2">
        <label for="descripcion" class="block text-sm font-semibold text-gray-700">Descripci├│n</label>
        <div class="relative mt-1.5">
            <span class="pointer-events-none absolute left-0 top-3 flex items-center pl-3.5 text-gray-400">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path></svg>
            </span>
            <textarea
                name="descripcion"
                id="descripcion"
                rows="4"
                placeholder="Describe la ruta, su dificultad, el paisajeÔÇª"
                class="block w-full rounded-lg border-gray-300 bg-gray-50 pl-11 pr-4 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm transition focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/30 @error('descripcion') border-red-300 @enderror"
            >{{ old('descripcion', $ruta->descripcion ?? '') }}</textarea>
        </div>
        @error('descripcion')
            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <!-- Origen -->
    <div class="sm:col-span-2">
        <label for="origen" class="block text-sm font-semibold text-gray-700">Punto de origen <span class="text-red-500">*</span></label>
        <div class="relative mt-1.5">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            </span>
            <input
                type="text"
                id="origen"
                name="origen"
                value="{{ old('origen', $ruta->origen ?? '') }}"
                placeholder="Barrio, direcci├│n o punto de partida"
                class="block w-full rounded-lg border-gray-300 bg-gray-50 pl-11 pr-4 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm transition focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/30 @error('origen') border-red-300 @enderror"
            >
        </div>
        @error('origen')
            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <!-- Destino -->
    <div class="sm:col-span-2">
        <label for="destino" class="block text-sm font-semibold text-gray-700">Punto de destino <span class="text-red-500">*</span></label>
        <div class="relative mt-1.5">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
            </span>
            <input
                type="text"
                id="destino"
                name="destino"
                value="{{ old('destino', $ruta->destino ?? '') }}"
                placeholder="Barrio, direcci├│n o destino final"
                class="block w-full rounded-lg border-gray-300 bg-gray-50 pl-11 pr-4 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm transition focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/30 @error('destino') border-red-300 @enderror"
            >
        </div>
        @error('destino')
            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <!-- Distancia -->
    <div>
        <label for="distancia" class="block text-sm font-semibold text-gray-700">Distancia</label>
        <div class="relative mt-1.5">
            <input
                type="number"
                step="0.01"
                min="0"
                id="distancia"
                name="distancia"
                value="{{ old('distancia', $ruta->distancia ?? '') }}"
                placeholder="0.00"
                class="block w-full rounded-lg border-gray-300 bg-gray-50 pl-4 pr-14 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm transition focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/30 @error('distancia') border-red-300 @enderror"
            >
            <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-4 text-sm font-medium text-gray-400">km</span>
        </div>
        @error('distancia')
            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <!-- Duraci├│n -->
    <div>
        <label for="duracion" class="block text-sm font-semibold text-gray-700">Duraci├│n estimada</label>
        <div class="relative mt-1.5">
            <input
                type="number"
                min="0"
                id="duracion"
                name="duracion"
                value="{{ old('duracion', $ruta->duracion ?? '') }}"
                placeholder="30"
                class="block w-full rounded-lg border-gray-300 bg-gray-50 pl-4 pr-14 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm transition focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/30 @error('duracion') border-red-300 @enderror"
            >
            <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-4 text-sm font-medium text-gray-400">min</span>
        </div>
        @error('duracion')
            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

</div>
