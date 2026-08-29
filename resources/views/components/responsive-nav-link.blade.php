@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-emerald-500 text-start text-base font-bold text-emerald-700 bg-emerald-50 focus:outline-none focus:text-emerald-800 focus:bg-emerald-100 focus:border-emerald-600 transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-600 hover:text-emerald-700 hover:bg-emerald-50 hover:border-emerald-200 focus:outline-none focus:text-emerald-700 focus:bg-emerald-50 focus:border-emerald-200 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>