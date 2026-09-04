@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-semibold text-sm text-[#a7b8b2]']) }}>
    {{ $value ?? $slot }}
</label>