<div class="mb-4">

    <label>Marca</label>

    <input
        type="text"
        name="marca"
        class="border rounded w-full"
        value="{{ old('marca', $bicicleta->marca ?? '') }}">
</div>

<div class="mb-4">

    <label>Modelo</label>

    <input
        type="text"
        name="modelo"
        class="border rounded w-full"
        value="{{ old('modelo', $bicicleta->modelo ?? '') }}">

</div>

<div class="mb-4">

    <label>Color</label>

    <input
        type="text"
        name="color"
        class="border rounded w-full"
        value="{{ old('color', $bicicleta->color ?? '') }}">

</div>

<div class="mb-4">

    <label>Número de serie</label>

    <input
        type="text"
        name="numero_serie"
        class="border rounded w-full"
        value="{{ old('numero_serie', $bicicleta->numero_serie ?? '') }}">

</div>

<div class="mb-4">

    <label>Tipo de bicicleta</label>

    <select
        name="tipo_bicicleta_id"
        class="border rounded w-full">

        @foreach($tipos as $tipo)

            <option value="{{ $tipo->id }}"
                @selected(old('tipo_bicicleta_id', $bicicleta->tipo_bicicleta_id ?? '') == $tipo->id)>
                {{ $tipo->nombre }}
            </option>

        @endforeach

    </select>

</div>

<div class="mb-4">

    <label>Barrio</label>

    <select
        name="barrio_id"
        class="border rounded w-full">

        @foreach($barrios as $barrio)

            <option value="{{ $barrio->id }}"
                @selected(old('barrio_id', $bicicleta->barrio_id ?? '') == $barrio->id)>
                {{ $barrio->nombre }}
            </option>

        @endforeach

    </select>

</div>