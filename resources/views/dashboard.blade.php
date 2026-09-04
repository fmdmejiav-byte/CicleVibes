<x-app-layout>
    <div class="cv-bg">
        <div class="mx-auto max-w-7xl px-5 py-10 sm:px-8 lg:py-14">
            <!-- ============ HERO ============ -->
            <section class="relative overflow-hidden rounded-[26px] border border-[rgba(0,255,136,0.18)] bg-[rgba(9,24,20,0.55)] p-7 shadow-[0_20px_50px_rgba(0,0,0,0.45)] backdrop-blur-xl sm:p-10 lg:p-12">
                <!-- ambient glows -->
                <div class="pointer-events-none absolute -top-24 -left-24 h-72 w-72 rounded-full bg-[#00ff88]/10 blur-3xl"></div>
                <div class="pointer-events-none absolute -bottom-28 -right-16 h-80 w-80 rounded-full bg-[#00e5ff]/10 blur-3xl"></div>

                <div class="relative grid items-center gap-10 lg:grid-cols-[1.1fr_0.9fr]">
                    <div class="animate-fade-in-up">
                        <span class="cv-badge">
                            <span class="h-2 w-2 rounded-full bg-[#00ff88] animate-pulse-soft" aria-hidden="true"></span>
                            Hola, {{ Auth::user()->nombre }} 👋
                        </span>

                        <h1 class="mt-5 text-4xl font-black leading-tight tracking-tight text-[#f1fff9] sm:text-5xl">
                            ¡A rodar por <span class="text-[#00ff88] cv-text-glow">Barranquilla</span>!
                        </h1>

                        <p class="mt-5 max-w-xl text-lg leading-relaxed text-[#a7b8b2]">
                            Descubre rutas pensadas para bicicleta, prioriza ciclorrutas y muévete por la ciudad de forma más inteligente.
                        </p>

                        <div class="mt-8 flex flex-wrap items-center gap-4">
                            <a href="{{ route('mapa') }}" class="cv-neon-button">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"></path></svg>
                                Explorar el mapa
                            </a>
                            <a href="{{ route('mapa') }}" class="cv-neon-ghost">
                                Ver ciclorrutas
                            </a>
                        </div>

                        <dl class="mt-10 flex items-center gap-8">
                            <div>
                                <dt class="text-xs font-bold uppercase tracking-widest text-[#6f817a]">Red ciclista</dt>
                                <dd class="mt-1 text-2xl font-black text-[#00e5ff]">40+ km</dd>
                            </div>
                            <div class="h-10 w-px bg-[rgba(0,255,136,0.15)]"></div>
                            <div>
                                <dt class="text-xs font-bold uppercase tracking-widest text-[#6f817a]">Perfil</dt>
                                <dd class="mt-1 text-2xl font-black text-[#00ff88]">Bicicleta</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- ============ Ilustración de mapa futurista ============ -->
                    <div class="relative hidden animate-map-in lg:block" aria-hidden="true">
                        <svg viewBox="0 0 420 300" class="w-full" role="img" aria-label="Ilustración de mapa con ruta en bicicleta">
                            <defs>
                                <linearGradient id="cv-main-route" x1="0%" y1="100%" x2="100%" y2="0%">
                                    <stop offset="0%" stop-color="#00b3a0" />
                                    <stop offset="100%" stop-color="#00ff88" />
                                </linearGradient>
                                <linearGradient id="cv-alt-route" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#00e5ff" stop-opacity="0.75" />
                                    <stop offset="100%" stop-color="#00e5ff" stop-opacity="0.15" />
                                </linearGradient>
                                <filter id="cv-glow" x="-50%" y="-50%" width="200%" height="200%">
                                    <feGaussianBlur stdDeviation="4" result="blur" />
                                    <feMerge>
                                        <feMergeNode in="blur" />
                                        <feMergeNode in="SourceGraphic" />
                                    </feMerge>
                                </filter>
                                <filter id="cv-soft-glow" x="-50%" y="-50%" width="200%" height="200%">
                                    <feGaussianBlur stdDeviation="7" result="blur" />
                                    <feMerge>
                                        <feMergeNode in="blur" />
                                        <feMergeNode in="SourceGraphic" />
                                    </feMerge>
                                </filter>
                            </defs>

                            <!-- superficie -->
                            <rect x="4" y="4" width="412" height="292" rx="22" fill="#08120f" stroke="rgba(0,255,136,0.25)" />

                            <!-- grid -->
                            <g stroke="rgba(0,229,255,0.07)" stroke-width="1">
                                <line x1="30" y1="8" x2="30" y2="296" />
                                <line x1="80" y1="8" x2="80" y2="296" />
                                <line x1="130" y1="8" x2="130" y2="296" />
                                <line x1="180" y1="8" x2="180" y2="296" />
                                <line x1="230" y1="8" x2="230" y2="296" />
                                <line x1="280" y1="8" x2="280" y2="296" />
                                <line x1="330" y1="8" x2="330" y2="296" />
                                <line x1="380" y1="8" x2="380" y2="296" />
                                <line x1="8" y1="40" x2="412" y2="40" />
                                <line x1="8" y1="90" x2="412" y2="90" />
                                <line x1="8" y1="140" x2="412" y2="140" />
                                <line x1="8" y1="190" x2="412" y2="190" />
                                <line x1="8" y1="240" x2="412" y2="240" />
                            </g>

                            <!-- avisos de manzanas -->
                            <g fill="rgba(0,255,136,0.05)" stroke="rgba(0,255,136,0.09)">
                                <rect x="46" y="58" width="66" height="40" rx="8" />
                                <rect x="128" y="48" width="58" height="50" rx="8" />
                                <rect x="300" y="40" width="70" height="46" rx="8" />
                                <rect x="246" y="110" width="58" height="50" rx="8" />
                                <rect x="46" y="120" width="58" height="44" rx="8" />
                                <rect x="300" y="176" width="70" height="48" rx="8" />
                                <rect x="130" y="210" width="66" height="44" rx="8" />
                            </g>

                            <!-- ruta secundaria (cian discontinua) -->
                            <path d="M60 258 C 120 240, 150 200, 200 178 S 300 130, 362 96"
                                fill="none" stroke="url(#cv-alt-route)" stroke-width="2.5" stroke-dasharray="1 7" stroke-linecap="round" />

                            <!-- halo de la ruta activa -->
                            <path d="M52 238 C 108 218, 140 176, 196 150 S 316 92, 368 56"
                                fill="none" stroke="#00ff88" stroke-width="14" stroke-linecap="round" opacity="0.12" filter="url(#cv-soft-glow)" />

                            <!-- ruta activa (verde neón) -->
                            <path d="M52 238 C 108 218, 140 176, 196 150 S 316 92, 368 56"
                                fill="none" stroke="url(#cv-main-route)" stroke-width="4.5" stroke-linecap="round" filter="url(#cv-glow)" />

                            <!-- flecha de dirección -->
                            <path d="M352 74 L 368 56 L 380 78" fill="none" stroke="#00ff88" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" filter="url(#cv-glow)" />

                            <!-- origen -->
                            <circle cx="52" cy="238" r="7" fill="#00ff88" filter="url(#cv-glow)" />
                            <circle cx="52" cy="238" r="14" fill="none" stroke="#00ff88" stroke-width="2" opacity="0.35" />

                            <!-- destino -->
                            <path d="M368 52 L 368 42" stroke="#ff4d5a" stroke-width="3" stroke-linecap="round" />
                            <path d="M360 52 A 9 9 0 0 1 376 52 Z" fill="#ff4d5a" filter="url(#cv-glow)" />
                            <circle cx="368" cy="52" r="2.5" fill="#1a0206" />

                            <!-- etiquetas glass -->
                            <g>
                                <rect x="74" y="196" width="92" height="26" rx="13" fill="rgba(9,24,20,0.82)" stroke="rgba(0,255,136,0.35)" />
                                <text x="120" y="213.5" text-anchor="middle" font-family="Inter, sans-serif" font-size="11" font-weight="700" fill="#00ff88">3.4 km · 14 min</text>

                                <rect x="242" y="128" width="86" height="24" rx="12" fill="rgba(9,24,20,0.82)" stroke="rgba(0,229,255,0.35)" />
                                <text x="285" y="144" text-anchor="middle" font-family="Inter, sans-serif" font-size="10" font-weight="700" fill="#00e5ff">Ciclorruta</text>

                                <rect x="150" y="226" width="64" height="24" rx="12" fill="rgba(9,24,20,0.82)" stroke="rgba(255,159,67,0.4)" />
                                <text x="182" y="242" text-anchor="middle" font-family="Inter, sans-serif" font-size="10" font-weight="700" fill="#ff9f43">Inicio</text>
                            </g>

                            <!-- borde interior luminoso -->
                            <rect x="4" y="4" width="412" height="292" rx="22" fill="none" stroke="rgba(0,255,136,0.18)" />
                        </svg>
                    </div>
                </div>
            </section>

            <!-- ============ CARDS ============ -->
            <section class="mt-8 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <!-- Card 1: Explora la ciudad -->
                <a href="{{ route('mapa') }}" class="cv-card cv-card-hover group p-7">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl border border-[rgba(0,255,136,0.25)] bg-[rgba(0,255,136,0.10)] text-[#00ff88] transition group-hover:shadow-[0_0_18px_rgba(0,255,136,0.4)]">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 20l-5.5-2.5a1 1 0 0 1-.5-.9V5a1 1 0 0 1 1.4-.9L9 6l6-2.5 5.5 2.5a1 1 0 0 1 .5.9v11.6a1 1 0 0 1-1.4.9L15 18l-6 2z"></path><path d="M9 5v15M15 3v15"></path></svg>
                    </span>
                    <h3 class="mt-5 text-xl font-extrabold text-[#f1fff9]">Explora la ciudad</h3>
                    <p class="mt-2 text-sm leading-relaxed text-[#a7b8b2]">Busca destinos, define tu origen y obtén rutas pensadas para pedaleo.</p>
                    <span class="link-neon mt-5 inline-flex items-center gap-1 text-sm">
                        Abrir el mapa
                        <svg class="link-arrow h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"></path></svg>
                    </span>
                </a>

                <!-- Card 2: Ciclorrutas -->
                <a href="{{ route('mapa') }}" class="cv-card cv-card--cyan cv-card-hover cv-card-hover--cyan group p-7">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl border border-[rgba(0,229,255,0.25)] bg-[rgba(0,229,255,0.10)] text-[#00e5ff] transition group-hover:shadow-[0_0_18px_rgba(0,229,255,0.35)]">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="18.5" cy="17.5" r="3.5"></circle><path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"></path></svg>
                    </span>
                    <h3 class="mt-5 text-xl font-extrabold text-[#f1fff9]">Ciclorrutas</h3>
                    <p class="mt-2 text-sm leading-relaxed text-[#a7b8b2]">Visualiza la infraestructura ciclista de la ciudad y prioriza tus recorridos por ella.</p>
                    <span class="link-neon mt-5 inline-flex items-center gap-1 text-sm">
                        Ver capa de ciclorrutas
                        <svg class="link-arrow h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"></path></svg>
                    </span>
                </a>

                <!-- Card 3: Tu recorrido -->
                <a href="{{ route('mapa') }}" class="cv-card cv-card-hover group p-7">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl border border-[rgba(0,255,136,0.25)] bg-[rgba(0,255,136,0.10)] text-[#00ff88] transition group-hover:shadow-[0_0_18px_rgba(0,255,136,0.4)]">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 19V9a2 2 0 0 1 2-2h2"></path><circle cx="14" cy="8" r="3"></circle><path d="M14 8v6a2 2 0 0 1-2 2H6"></path></svg>
                    </span>
                    <h3 class="mt-5 text-xl font-extrabold text-[#f1fff9]">Tu recorrido</h3>
                    <p class="mt-2 text-sm leading-relaxed text-[#a7b8b2]">Conoce distancia, tiempo estimado e indicaciones paso a paso antes de salir.</p>
                    <span class="link-neon mt-5 inline-flex items-center gap-1 text-sm">
                        Planear recorrido
                        <svg class="link-arrow h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"></path></svg>
                    </span>
                </a>
            </section>
        </div>
    </div>
</x-app-layout>