@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'cv-input block w-full rounded-xl border px-4 py-2.5 text-sm text-[#f1fff9] shadow-sm focus:outline-none']) }}>