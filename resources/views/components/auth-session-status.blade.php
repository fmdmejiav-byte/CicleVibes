@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-[#00ff88]']) }}>
        {{ $status }}
    </div>
@endif