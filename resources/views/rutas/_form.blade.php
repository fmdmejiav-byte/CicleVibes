<div class="mb-4">

    <label>Nombre</label>

    <input
        type="text"
        name="nombre"
        class="border rounded w-full"
        value="{{ old('nombre', $ruta->nombre ?? '') }}">

</div>

<div class="mb-4">

    <label>Descripción</label>

    <textarea
        name="descripcion"
        rows="4"
        class="border rounded w-full">{{ old('descripcion', $ruta->descripcion ?? '') }}</textarea>

</div>

<div class="mb-4">

    <label>Origen</label>

    <input
        type="text"
        name="origen"
        class="border rounded w-full"
        value="{{ old('origen', $ruta->origen ?? '') }}">

</div>

<div class="mb-4">

    <label>Destino</label>

    <input
        type="text"
        name="destino"
        class="border rounded w-full"
        value="{{ old('destino', $ruta->destino ?? '') }}">

</div>

<div class="mb-4">

    <label>Distancia (km)</label>

    <input
        type="number"
        step="0.01"
        min="0"
        name="distancia"
        class="border rounded w-full"
        value="{{ old('distancia', $ruta->distancia ?? '') }}">

</div>

<div class="mb-4">

    <label>Duración (minutos)</label>

    <input
        type="number"
        min="0"
        name="duracion"
        class="border rounded w-full"
        value="{{ old('duracion', $ruta->duracion ?? '') }}">

</div>
