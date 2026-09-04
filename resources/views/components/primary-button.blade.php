<button {{ $attributes->merge(['type' => 'submit', 'class' => 'cv-neon-button text-xs uppercase tracking-widest']) }}>
    {{ $slot }}
</button>