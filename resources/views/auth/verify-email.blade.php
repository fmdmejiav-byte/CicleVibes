<x-guest-layout>
    <div class="cv-card p-8 sm:p-10">
        <h2 class="text-2xl font-extrabold text-[#f1fff9]">Verifica tu correo</h2>
        <p class="mt-1.5 text-sm text-[#a7b8b2]">
            {{ __('¡Gracias por registrarte! Antes de comenzar, verifica tu dirección de correo usando el enlace que te enviamos. Si no recibiste el correo, con gusto te enviaremos otro.') }}
        </p>

        @if (session('status') == 'verification-link-sent')
            <div class="mt-6 rounded-xl border border-[rgba(0,255,136,0.3)] bg-[rgba(0,255,136,0.08)] p-3.5 text-sm font-medium text-[#00ff88]">
                {{ __('Se ha enviado un nuevo enlace de verificación a la dirección de correo que registraste.') }}
            </div>
        @endif

        <div class="mt-8 flex flex-wrap items-center justify-between gap-4">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf

                <div>
                    <x-primary-button>
                        {{ __('Reenviar correo de verificación') }}
                    </x-primary-button>
                </div>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <button type="submit" class="link-neon text-sm">
                    {{ __('Cerrar sesión') }}
                </button>
            </form>
        </div>
    </div>
</x-guest-layout>