{{-- Modal de Términos y Condiciones / Política de Datos.
     Requiere que exista la propiedad Alpine `termsOpen` en el scope padre. --}}
<div x-show="termsOpen" x-cloak
     class="fixed inset-0 z-[1300] flex items-center justify-center px-4 py-6"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @keydown.escape.window="termsOpen = false">

    <div class="absolute inset-0 bg-[#02140d]/85 backdrop-blur-sm" @click="termsOpen = false" aria-hidden="true"></div>

    <div x-show="termsOpen" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-3 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-3 scale-95"
         role="dialog" aria-modal="true" aria-label="Términos y Condiciones y Política de Tratamiento de Datos"
         class="cv-card relative z-10 flex max-h-[80vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl">

        <div class="flex items-start justify-between gap-4 border-b border-[rgba(0,255,136,0.12)] px-5 py-4">
            <div>
                <h3 class="text-lg font-black text-[#f1fff9]">Términos y Condiciones</h3>
                <p class="text-xs text-[#a7b8b2]">y Política de Tratamiento de Datos de CicleVibes</p>
            </div>
            <button type="button" @click="termsOpen = false"
                class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full text-[#a7b8b2] transition hover:bg-[rgba(0,255,136,0.1)] hover:text-[#00ff88]"
                aria-label="Cerrar">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"></path></svg>
            </button>
        </div>

        <div class="overflow-y-auto px-5 py-4">
            @include('auth.partials._data-terms')
        </div>

        <div class="flex justify-end border-t border-[rgba(0,255,136,0.12)] px-5 py-3.5">
            <button type="button" @click="termsOpen = false" class="cv-neon-button !px-5 !py-2 !text-sm">
                Entendido
            </button>
        </div>
    </div>
</div>