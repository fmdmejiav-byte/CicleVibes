{{-- Sección "Continuar con Google" de CicleVibes.
     Requiere en el scope Alpine padre: `googleTerms` (checkbox) y `termsOpen`
     (modal de Términos y Política de Tratamiento de Datos).
     Solo se muestra si las credenciales OAuth están configuradas. --}}
@if (config('services.google.client_id'))
    <div class="mt-6">
        <div class="flex items-center gap-3" aria-hidden="true">
            <span class="h-px flex-1 bg-[rgba(0,255,136,0.12)]"></span>
            <span class="text-[11px] font-bold uppercase tracking-widest text-[#6f817a]">o continúa con</span>
            <span class="h-px flex-1 bg-[rgba(0,255,136,0.12)]"></span>
        </div>

        <button
            type="button"
            @click="googleTerms && (window.location.href = '{{ route('auth.google') }}')"
            :disabled="!googleTerms"
            class="cv-google-button mt-5"
            :class="!googleTerms ? '!opacity-45' : ''"
            aria-label="Continuar con Google"
        >
            @include('auth.partials._google-g')
            <span>Continuar con Google</span>
        </button>

        <label class="mt-4 flex cursor-pointer items-start gap-2.5 select-none">
            <input
                type="checkbox"
                x-model="googleTerms"
                class="mt-0.5 h-4 w-4 rounded border-[rgba(0,255,136,0.3)] bg-[rgba(9,24,20,0.6)] text-[#00ff88] accent-[#00ff88] focus:ring-[#00ff88]/40"
            >
            <span class="text-xs leading-snug text-[#a7b8b2]">
                Antes de continuar con Google, aceptas los
                <button type="button" @click="termsOpen = true" class="link-neon inline font-semibold">Términos y Condiciones</button>
                y la
                <button type="button" @click="termsOpen = true" class="link-neon inline font-semibold">Política de Tratamiento de Datos</button>.
                Google solo comparte contigo la información autorizada (nombre, correo y foto) para crear tu cuenta.
            </span>
        </label>

        @include('auth.partials._terms-modal')
    </div>
@endif