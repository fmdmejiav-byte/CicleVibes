@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center rounded-lg border border-[rgba(0,255,136,0.45)] bg-[rgba(0,255,136,0.10)] px-3 py-1.5 text-sm font-bold leading-5 text-[#00ff88] shadow-[0_0_14px_rgba(0,255,136,0.18)] focus:outline-none focus:ring-2 focus:ring-[#00ff88]/50 transition duration-150 ease-in-out'
            : 'inline-flex items-center rounded-lg border border-transparent px-3 py-1.5 text-sm font-semibold leading-5 text-[#a7b8b2] hover:text-[#00ff88] hover:bg-[rgba(0,255,136,0.06)] focus:outline-none focus:text-[#00ff88] focus:ring-2 focus:ring-[#00ff88]/40 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>