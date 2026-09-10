<x-app-layout>
    <div class="cv-bg-map relative overflow-hidden"
         x-data="CicleMap({{ json_encode($mapConfig) }})">

        <!-- Mapa (casi pantalla completa, tiles oscuros) -->
        <div id="cicle-map" class="map-container-full w-full animate-map-in"></div>

        <!-- ============ Búsqueda (superior) ============ -->
        <div class="absolute left-3 right-3 top-3 z-[1000] flex justify-center lg:right-[440px] lg:left-3 lg:justify-start">
            <div class="w-full max-w-xl">
                <div class="cv-glass-soft flex items-center overflow-hidden rounded-full shadow-[0_10px_30px_rgba(0,0,0,0.45)]">
                    <span class="flex items-center pl-4 text-[#00ff88]">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                    </span>
                    <input
                        type="text"
                        x-model="query"
                        @input="triggerSearch()"
                        placeholder="¿A dónde quieres rodar? (ej. Universidad del Norte)"
                        class="w-full border-0 bg-transparent px-3 py-3.5 text-sm text-[#f1fff9] placeholder:text-[#6f817a] focus:outline-none focus:ring-0"
                    >
                    <button
                        x-show="query.length > 0"
                        @click="query = ''; results = []; selectedPlace = null; status = ''"
                        class="flex items-center pr-4 text-[#a7b8b2] transition hover:text-[#00ff88]"
                        aria-label="Limpiar búsqueda"
                    >
                        <svg x-cloak class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Resultados de búsqueda -->
                <div x-show="searching" x-cloak class="cv-card mt-2 px-4 py-3 text-sm text-[#a7b8b2]">
                    <span class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity="0.25"></circle><path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path></svg>
                        Buscando…
                    </span>
                </div>
                <div x-show="results.length > 0" x-cloak class="cv-card mt-2 max-h-72 overflow-y-auto rounded-2xl">
                    <template x-for="(r, i) in results" :key="i">
                        <button @click="selectResult(r)"
                            class="flex w-full items-start gap-3 border-b border-[rgba(0,255,136,0.08)] px-4 py-3 text-left transition hover:bg-[rgba(0,255,136,0.07)] last:border-0">
                            <span class="mt-0.5 flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl border border-[rgba(0,229,255,0.25)] bg-[rgba(0,229,255,0.1)] text-[#00e5ff]">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate font-semibold text-[#f1fff9]" x-text="r.name"></span>
                                <span class="block truncate text-xs text-[#a7b8b2]" x-text="r.address"></span>
                            </span>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <!-- ============ Controles de capa (izquierda) ============ -->
        <div class="absolute left-3 top-[72px] z-[1000] flex flex-row flex-wrap items-start gap-2 lg:top-[74px] lg:flex-col">
            <button @click="setOriginFromUser()" :title="locating ? 'Localizando…' : 'Mi ubicación'"
                class="flex h-11 w-11 items-center justify-center rounded-full border border-[rgba(0,255,136,0.18)] bg-[rgba(9,24,20,0.82)] text-[#00ff88] shadow-lg backdrop-blur transition hover:bg-[rgba(0,255,136,0.14)] hover:shadow-[0_0_18px_rgba(0,255,136,0.3)]"
                :class="{ 'map-btn-active': locationSet }">
                <svg x-show="!locating" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M12 2v2M12 20v2M2 12h2M20 12h2"></path></svg>
                <svg x-show="locating" x-cloak class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity="0.25"></circle><path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path></svg>
            </button>

            <button @click="toggleBicicletas()" :title="showBicicletas ? 'Ocultar bicicletas' : 'Mostrar bicicletas'"
                class="flex h-11 w-11 items-center justify-center rounded-full border border-[rgba(0,229,255,0.2)] bg-[rgba(9,24,20,0.82)] text-[#00e5ff] shadow-lg backdrop-blur transition hover:bg-[rgba(0,229,255,0.12)]"
                :class="showBicicletas ? 'shadow-[0_0_14px_rgba(0,229,255,0.35)]' : 'opacity-60'">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="18.5" cy="17.5" r="3.5"></circle><path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"></path></svg>
            </button>

            <button @click="toggleCyclorutas()" :title="showCyclorutas ? 'Ocultar ciclorrutas' : 'Mostrar ciclorrutas'"
                class="flex h-11 items-center gap-2 rounded-full border border-[rgba(0,255,136,0.18)] bg-[rgba(9,24,20,0.82)] pl-3 pr-4 text-[#00ff88] shadow-lg backdrop-blur transition hover:bg-[rgba(0,255,136,0.14)]"
                :class="showCyclorutas ? 'shadow-[0_0_14px_rgba(0,255,136,0.35)]' : 'opacity-60'">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 1.82.33z"></path><path d="M4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06A2 2 0 1 1 7 9.95l-.06.06A1.65 1.65 0 0 0 4.6 9z"></path><path d="m14 5 2 -0.5"></path></svg>
                <span class="pr-1 text-xs font-bold text-[#00ff88]">Ciclorrutas</span>
            </button>
        </div>

        <!-- ============ Panel de planificación / rutas / navegación ============ -->
        <div x-cloak
             class="route-panel cv-glass absolute bottom-3 left-3 right-3 z-[1000] flex max-h-[55vh] flex-col overflow-hidden rounded-3xl shadow-[0_18px_50px_rgba(0,0,0,0.55)] lg:top-16 lg:bottom-3 lg:left-auto lg:right-3 lg:max-h-[calc(100vh-5.5rem)] lg:w-[420px]"
             :class="panelCollapsed ? 'route-panel--collapsed max-h-[58px] lg:max-h-[58px]' : ''">

            <!-- Cabecera del panel (siempre visible) -->
            <div class="flex items-center justify-between gap-3 rounded-t-3xl border-b border-[rgba(0,255,136,0.12)] bg-[rgba(9,24,20,0.5)] px-4 py-3">
                <div class="flex min-w-0 items-center gap-2">
                    <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-xl bg-[linear-gradient(135deg,#00ff88,#00b3a0)] text-[#02140d]">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="18.5" cy="17.5" r="3.5"></circle><path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"></path></svg>
                    </span>
                    <div class="min-w-0">
                        <h3 class="truncate font-black tracking-tight text-[#f1fff9]"
                            x-text="mode === 'navigating' ? 'Navegando' : (mode === 'routes' ? 'Elige tu ruta' : 'Planificar ruta')"></h3>
                        <p x-show="showCyclorutas && !panelCollapsed" x-cloak class="flex items-center gap-1 text-[11px] font-bold uppercase tracking-wider text-[#00e5ff]">
                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="18.5" cy="17.5" r="3.5"></circle><path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"></path></svg>
                            Ciclorrutas
                        </p>
                    </div>
                </div>

                <!-- Resumen compacto al colapsar (navegando) -->
                <div x-show="panelCollapsed && mode === 'navigating'" x-cloak class="flex items-center gap-2 text-sm font-black text-[#f1fff9]">
                    <span x-text="formatDur(timeRemainingMin)" class="text-[#00ff88]"></span>
                    <span class="text-[#6f817a]">·</span>
                    <span x-text="formatKm(distanceRemainingKm)" class="text-[#00e5ff]"></span>
                </div>

                <button @click="togglePanel()"
                    :title="panelCollapsed ? 'Expandir panel' : 'Colapsar panel'"
                    class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full text-[#a7b8b2] transition hover:bg-[rgba(0,255,136,0.1)] hover:text-[#00ff88]">
                    <svg class="h-5 w-5 transition-transform duration-300" :class="panelCollapsed ? 'rotate-180' : ''"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"></path></svg>
                </button>
            </div>

            <!-- Cuerpo del panel -->
            <div x-show="!panelCollapsed" class="flex flex-col overflow-hidden">
                <!-- ===================== PLAN / RUTAS / PICK ===================== -->
                <template x-if="mode === 'plan' || mode === 'routes' || mode === 'pick'">
                    <div class="flex flex-col overflow-hidden">
                        <div class="flex-1 space-y-3 overflow-y-auto p-4">

                            <!-- Resumen "Tu recorrido" (cuando hay ruta seleccionada) -->
                            <div x-show="mode === 'routes' && selectedRoute()" x-cloak class="rounded-2xl border border-[rgba(0,255,136,0.18)] bg-[rgba(0,255,136,0.06)] p-4">
                                <div class="flex items-center justify-between">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#a7b8b2]">Tu recorrido</p>
                                    <span class="cv-badge">
                                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="18.5" cy="17.5" r="3.5"></circle><path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"></path></svg>
                                        <span x-text="selectedRoute().via_ciclorutas ? 'Ciclorrutas' : 'Bicicleta'"></span>
                                    </span>
                                </div>
                                <div class="mt-3 grid grid-cols-3 gap-3 text-center">
                                    <div>
                                        <p class="text-2xl font-black text-[#00e5ff]" x-text="formatKm(routeStats(selectedRoute()).distance_km)"></p>
                                        <p class="text-[11px] font-semibold text-[#a7b8b2]">Distancia</p>
                                    </div>
                                    <div>
                                        <p class="text-2xl font-black text-[#00ff88]" x-text="formatDur(routeStats(selectedRoute()).duration_min)"></p>
                                        <p class="text-[11px] font-semibold text-[#a7b8b2]">Tiempo</p>
                                    </div>
                                    <div>
                                        <p class="text-2xl font-black text-[#f1fff9]" x-text="(selectedRoute().steps?.length ?? 0)"></p>
                                        <p class="text-[11px] font-semibold text-[#a7b8b2]">Pasos</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Lugar seleccionado de la búsqueda -->
                            <div x-show="selectedPlace" x-cloak class="rounded-2xl border border-[rgba(0,255,136,0.15)] bg-[rgba(9,24,20,0.5)] p-3">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#00e5ff]">Lugar seleccionado</p>
                                <p class="mt-0.5 truncate font-semibold text-[#f1fff9]" x-text="selectedPlace?.name"></p>
                                <p class="line-clamp-2 text-xs text-[#a7b8b2]" x-text="selectedPlace?.address"></p>
                                <div class="mt-3 flex gap-2">
                                    <button @click="setAsOriginFromSearch()" class="cv-neon-ghost !px-3 !py-1.5 !text-xs">Usar como origen</button>
                                    <button @click="setAsDestinationFromSearch()" class="flex-1 rounded-xl border border-[rgba(255,77,90,0.3)] bg-[rgba(255,77,90,0.1)] px-3 py-1.5 text-xs font-bold text-[#ff7d88] transition hover:bg-[rgba(255,77,90,0.18)]">Como destino</button>
                                </div>
                            </div>

                            <!-- Priorizar ciclorrutas -->
                            <div @click="togglePriorizarCiclorutas()"
                                class="flex cursor-pointer items-center justify-between rounded-2xl border border-[rgba(0,255,136,0.15)] bg-[rgba(9,24,20,0.5)] p-3 transition hover:border-[rgba(0,255,136,0.35)]">
                                <span class="flex items-center gap-2.5 text-sm font-bold text-[#f1fff9]">
                                    <svg class="h-5 w-5 text-[#00e5ff]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="18.5" cy="17.5" r="3.5"></circle><path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"></path></svg>
                                    Priorizar ciclorrutas
                                </span>
                                <span class="cv-switch" :class="priorizarCiclorutas ? 'is-on' : ''">
                                    <span></span>
                                </span>
                            </div>

                            <!-- Selector de perfil de ruta -->
                            <div x-show="mode !== 'navigating'" x-cloak>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#a7b8b2]">Perfil de ruta</p>
                                <div class="profile-scroll mt-2 flex gap-2 overflow-x-auto pb-1">
                                    <template x-for="p in profilesConfig" :key="p.key">
                                        <button @click="selectProfile(p.key)" :title="p.description"
                                            class="cv-profile-chip"
                                            :class="selectedProfile === p.key ? 'is-active' : ''">
                                            <span x-text="p.emoji"></span>
                                            <span x-text="p.label"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <!-- Help de clic en el mapa -->
                            <div x-show="pickHint" x-cloak class="rounded-2xl border border-[rgba(0,229,255,0.25)] bg-[rgba(0,229,255,0.08)] px-4 py-3 text-center text-sm font-semibold text-[#00e5ff]">
                                <span x-text="pickHint"></span>
                            </div>

                            <!-- Origen -->
                            <div class="rounded-2xl border border-[rgba(0,255,136,0.15)] bg-[rgba(9,24,20,0.5)] p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#00ff88]">Origen</p>
                                    <div class="flex flex-wrap gap-1.5">
                                        <button @click="setOriginFromUser()" class="rounded-lg bg-[linear-gradient(135deg,#00ff88,#00b3a0)] px-2 py-1 text-[11px] font-bold text-[#02140d] shadow-[0_0_12px_rgba(0,255,136,0.3)] transition hover:brightness-110">Mi ubicación</button>
                                        <button @click="pickOnMap('origin')" class="rounded-lg border border-[rgba(0,255,136,0.3)] bg-[rgba(0,255,136,0.08)] px-2 py-1 text-[11px] font-bold text-[#00ff88] transition hover:bg-[rgba(0,255,136,0.15)]">Señalar en mapa</button>
                                    </div>
                                </div>
                                <p class="mt-1 truncate text-sm font-medium text-[#eaf6f0]" x-text="originLabel(origin)"></p>
                            </div>

                            <!-- Destino -->
                            <div class="rounded-2xl border border-[rgba(255,77,90,0.25)] bg-[rgba(255,77,90,0.06)] p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#ff7d88]">Destino</p>
                                    <button @click="pickOnMap('destination')" class="rounded-lg border border-[rgba(255,77,90,0.35)] bg-[rgba(255,77,90,0.08)] px-2 py-1 text-[11px] font-bold text-[#ff7d88] transition hover:bg-[rgba(255,77,90,0.15)]">Señalar en mapa</button>
                                </div>
                                <p class="mt-1 truncate text-sm font-medium text-[#eaf6f0]" x-text="destinationLabel(destination)"></p>
                            </div>

                            <div x-show="origin && destination" x-cloak class="flex gap-2">
                                <button @click="reverseOriginDestination()" class="flex-1 rounded-xl border border-[rgba(0,255,136,0.15)] bg-[rgba(9,24,20,0.5)] px-3 py-2 text-xs font-semibold text-[#a7b8b2] transition hover:border-[#00ff88] hover:text-[#00ff88]">Intercambiar</button>
                                <button @click="clearRoute()" class="flex-1 rounded-xl border border-[rgba(255,77,90,0.2)] bg-[rgba(9,24,20,0.5)] px-3 py-2 text-xs font-semibold text-[#a7b8b2] transition hover:border-[#ff4d5a] hover:text-[#ff4d5a]">Limpiar</button>
                            </div>

                            <!-- Resultados por perfil -->
                            <div x-show="mode === 'routes' && profileResults.length > 0" x-cloak class="space-y-2">
                                <div x-show="priorizarCiclorutas && !ciclorutaConnection" x-cloak class="rounded-2xl border border-[rgba(255,159,67,0.35)] bg-[rgba(255,159,67,0.08)] p-3">
                                    <p class="text-sm font-bold text-[#ffb25d]">No existe una conexión ciclista completa con los datos disponibles.</p>
                                    <p class="mt-0.5 text-xs text-[#a7b8b2]">Mostramos la alternativa por calles (OSRM) para que puedas llegar igualmente.</p>
                                </div>
                                <template x-for="item in profileResults" :key="item.profile">
                                    <button @click="selectProfile(item.profile)"
                                        class="flex w-full items-start gap-3 rounded-2xl border p-3 text-left transition"
                                        :class="selectedProfile === item.profile ? 'border-[rgba(0,255,136,0.5)] bg-[rgba(0,255,136,0.07)] shadow-[0_0_18px_rgba(0,255,136,0.15)]' : 'cv-glass-soft hover:border-[rgba(0,255,136,0.35)]'">
                                        <span x-show="item.route" class="cv-step-num mt-0.5"
                                            :class="selectedProfile === item.profile ? 'cv-step-num--active' : ''">
                                            <span x-text="selectedProfile === item.profile ? '✓' : item.emoji"></span>
                                        </span>
                                        <span x-show="!item.route" class="cv-step-num mt-0.5 !bg-[rgba(255,77,90,0.12)] !text-[#ff4d5a]">
                                            <span x-text="item.emoji"></span>
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="flex items-center justify-between gap-2">
                                                <span class="truncate font-bold text-[#f1fff9]" x-text="item.label"></span>
                                                <template x-if="item.route">
                                                    <span class="flex-shrink-0 cv-badge" :class="item.route.via_ciclorutas ? '' : 'cv-badge--cyan'"
                                                        x-text="routeBadge(item.route)"></span>
                                                </template>
                                            </span>
                                            <template x-if="item.route">
                                                <span class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                                                    <span class="font-bold text-[#00e5ff]" x-text="formatKm(item.route.distance_km)"></span>
                                                    <span class="text-[#5f726a]">·</span>
                                                    <span class="font-semibold text-[#d9e8e0]" x-text="formatDur(item.route.duration_min)"></span>
                                                    <span class="text-[#5f726a]">·</span>
                                                    <span class="font-semibold text-[#00ff88]" x-text="routeAvgSpeed(item.route)"></span>
                                                    <template x-if="item.route.elevation_available && item.route.ascent_m !== null">
                                                        <span>
                                                            <span class="text-[#5f726a]">·</span>
                                                            <span class="font-semibold text-[#ffb25d]" x-text="formatAscent(item.route.ascent_m)"></span>
                                                        </span>
                                                    </template>
                                                </span>
                                            </template>
                                            <template x-if="item.route.note">
                                                <span class="mt-1 block text-xs text-[#a7b8b2]" x-text="item.route.note"></span>
                                            </template>
                                            <span x-show="item.route && item.route.cicloruta_coverage_pct > 0" x-cloak class="mt-1.5 block space-y-1">
                                                <span class="flex items-center gap-1.5 text-xs font-semibold text-[#00e5ff]">
                                                    <span class="h-1.5 w-24 overflow-hidden rounded-full bg-[rgba(0,229,255,0.15)]">
                                                        <span class="block h-full rounded-full bg-[#00e5ff]" :style="'width: ' + item.route.cicloruta_coverage_pct + '%'"></span>
                                                    </span>
                                                    <span x-text="item.route.cicloruta_coverage_pct + '% del trayecto por ciclorruta'"></span>
                                                </span>
                                                <span x-show="item.route.ciclorutas_used && item.route.ciclorutas_used.length" class="block text-xs text-[#a7b8b2]">
                                                    <span class="font-semibold text-[#d9e8e0]">Ciclorrutas:</span>
                                                    <span x-text="item.route.ciclorutas_used.join(' · ')"></span>
                                                </span>
                                            </span>
                                            <span x-show="item.route.metadata" class="mt-1.5 block space-y-1.5">
                                                <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                                    <span class="cv-safety-chip" :class="safetyChipClass(item.route.metadata)"
                                                        x-text="safetyChipText(item.route.metadata)"></span>
                                                    <template x-if="item.route.metadata.safety_score !== null">
                                                        <span class="text-xs font-semibold text-[#9fb5ad]"
                                                            x-text="'Confianza del análisis: ' + item.route.metadata.safety_confidence_label"></span>
                                                    </template>
                                                </span>
                                                <template x-if="selectedProfile === item.profile && item.route.metadata.safety_explanation">
                                                    <span class="block text-xs leading-relaxed text-[#a7b8b2]"
                                                        x-text="item.route.metadata.safety_explanation"></span>
                                                </template>
                                                <template x-if="selectedProfile === item.profile && item.route.metadata.safety_signals && item.route.metadata.safety_signals.length">
                                                    <span class="block text-xs text-[#a7b8b2]">
                                                        <span class="font-semibold text-[#d9e8e0]">Datos disponibles:</span>
                                                        <template x-for="s in item.route.metadata.safety_signals" :key="s.type">
                                                            <span class="block" x-text="'• ' + s.name + ': ' + Math.round(s.score) + '/100'"></span>
                                                        </template>
                                                    </span>
                                                </template>
                                            </span>
                                        </span>
                                    </button>
                                </template>
                            </div>

                            <!-- Botón Calcular / Iniciar -->
                            <div class="pt-1">
                                <template x-if="mode === 'routes' && selectedRoute()">
                                    <button @click="startNavigation()"
                                        class="cv-neon-button w-full">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"></path></svg>
                                        INICIAR RUTA
                                    </button>
                                </template>
                                <template x-if="mode !== 'routes'">
                                    <button @click="calculateProfiles()"
                                        :disabled="!origin || !destination || routing"
                                        class="cv-neon-button w-full" :class="(!origin || !destination || routing) ? '!shadow-none' : ''">
                                        <span x-show="!routing" class="inline-flex items-center gap-2">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="19" r="3"></circle><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"></path><circle cx="18" cy="5" r="3"></circle></svg>
                                            Calcular rutas para bici
                                        </span>
                                        <span x-show="routing" x-cloak class="inline-flex items-center gap-2">
                                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity="0.25"></circle><path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path></svg>
                                            Calculando…
                                        </span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- ===================== NAVEGANDO ===================== -->
                <template x-if="mode === 'navigating'">
                    <div class="flex flex-col overflow-hidden">
                        <!-- Desviación -->
                        <div x-show="deviating" x-cloak class="nav-danger px-4 py-3 text-white">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-bold">Te has desviado de la ruta</p>
                                    <p class="text-xs text-white/80">Tu posición se alejó del camino calculado.</p>
                                </div>
                                <button @click="confirmRecalculate()" :disabled="routing"
                                    class="rounded-lg bg-white px-3 py-2 text-xs font-bold text-red-600 shadow hover:bg-red-50">
                                    <span x-show="!routing">Recalcular</span>
                                    <span x-show="routing" x-cloak>…</span>
                                </button>
                            </div>
                        </div>

                        <!-- HUD principal -->
                        <div class="cv-glass-soft mx-4 mt-4 rounded-2xl px-4 py-4">
                            <div class="flex items-center justify-between">
                                <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-[#00e5ff]">
                                    <span class="h-2 w-2 rounded-full bg-[#00ff88] animate-pulse-soft" aria-hidden="true"></span>
                                    Navegando
                                </p>
                                <span class="cv-badge">Perfil bici</span>
                            </div>

                            <p class="mt-3 text-lg font-bold leading-snug text-[#f1fff9]"
                               x-text="routeInfo && routeInfo.steps && routeInfo.steps[currentStep] ? routeInfo.steps[currentStep].instruction : 'Gira según las indicaciones del mapa.'"></p>

                            <div class="mt-4 flex items-center justify-around">
                                <div class="text-center">
                                    <p class="text-2xl font-black text-[#00e5ff]" x-text="formatKm(distanceRemainingKm)"></p>
                                    <p class="text-[11px] font-semibold text-[#a7b8b2]">Restante</p>
                                </div>
                                <div class="h-9 w-px bg-[rgba(255,255,255,0.1)]"></div>
                                <div class="text-center">
                                    <p class="text-2xl font-black text-[#00ff88]" x-text="formatDur(timeRemainingMin)"></p>
                                    <p class="text-[11px] font-semibold text-[#a7b8b2]">Tiempo estimado</p>
                                </div>
                                <div class="h-9 w-px bg-[rgba(255,255,255,0.1)]"></div>
                                <div class="text-center">
                                    <p class="text-2xl font-black text-[#f1fff9]" x-text="(routeInfo?.steps?.length ?? 0)"></p>
                                    <p class="text-[11px] font-semibold text-[#a7b8b2]">Pasos</p>
                                </div>
                            </div>
                        </div>

                        <!-- Acciones de navegación -->
                        <div class="flex gap-2 p-4">
                            <button @click="stopNavigation()"
                                class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-[#ff4d5a] px-4 py-3 text-sm font-bold text-white shadow-[0_0_18px_rgba(255,77,90,0.4)] transition hover:bg-[#ff2e40]">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="6" width="12" height="12" rx="2"></rect></svg>
                                Detener
                            </button>
                        </div>

                        <!-- Instrucciones detalladas (consolidadas por calle) -->
                        <div x-show="routeInfo && routeInfo.steps && routeInfo.steps.length" x-cloak
                             class="overflow-y-auto border-t border-[rgba(0,255,136,0.12)] px-4 pt-3 pb-4">
                            <div class="mb-2 flex items-center justify-between px-1">
                                <div class="text-[11px] font-bold uppercase tracking-wider text-[#a7b8b2]">Instrucciones</div>
                                <button @click="togglePanel()" class="cv-badge !text-[10px] uppercase tracking-wide">
                                    Colapsar
                                </button>
                            </div>
                            <div class="space-y-1.5">
                                <template x-for="(s, i) in (routeInfo?.steps ?? [])" :key="i">
                                    <div class="flex items-start gap-3 rounded-xl border px-3 py-2 transition"
                                         :class="i === currentStep && mode === 'navigating' ? 'border-[rgba(0,255,136,0.35)] bg-[rgba(0,255,136,0.08)]' : 'border-transparent hover:bg-[rgba(255,255,255,0.03)]'">
                                        <span class="cv-step-num mt-0.5" :class="i === currentStep && mode === 'navigating' ? 'cv-step-num--active' : ''"
                                              x-text="String(i + 1).padStart(2, '0')"></span>
                                        <span class="min-w-0 flex-1 text-sm font-medium text-[#eaf6f0]" x-text="s.instruction"></span>
                                        <span class="cv-step-distance mt-1" x-text="formatDist(s.distance_m)"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- ============ Leyenda (izquierda / colapsable) ============ -->
        <div class="absolute left-3 top-[124px] z-[999] lg:top-auto lg:bottom-4">
            <div class="cv-glass-soft w-auto min-w-[150px] rounded-2xl p-3">
                <button @click="toggleLegend()"
                    class="flex w-full items-center justify-between gap-3 text-[11px] font-bold uppercase tracking-wider text-[#a7b8b2] transition hover:text-[#f1fff9]">
                    Leyenda
                    <svg class="h-4 w-4 transition-transform duration-300" :class="legendOpen ? 'rotate-180' : ''"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"></path></svg>
                </button>
                <div x-show="legendOpen" x-cloak class="mt-2.5 space-y-1.5">
                    <div class="flex items-center gap-2"><span class="inline-block h-1 w-6 rounded-full bg-[#00ff88] shadow-[0_0_8px_rgba(0,255,136,0.8)]"></span><span class="text-xs font-semibold text-[#f1fff9]">Ruta activa</span></div>
                    <div class="flex items-center gap-2"><span class="inline-block h-1 w-6 rounded-full bg-[#00e5ff]"></span><span class="text-xs text-[#a7b8b2]">Carril preferente</span></div>
                    <div class="flex items-center gap-2"><span class="inline-block h-1 w-6 rounded-full bg-[#ff4d5a]"></span><span class="text-xs text-[#a7b8b2]">Ciclorruta en calzada</span></div>
                    <div class="flex items-center gap-2"><span class="inline-block h-1 w-6 rounded-full bg-[#ff9f43]"></span><span class="text-xs text-[#a7b8b2]">Ciclorruta en andén</span></div>
                    <div class="flex items-center gap-2"><span class="inline-block h-1 w-6 rounded-full bg-[#ffd60a]"></span><span class="text-xs text-[#a7b8b2]">Ciclobanda</span></div>
                </div>
            </div>
        </div>

        <!-- ============ Estado (barra de información) ============ -->
        <div x-show="status" x-cloak class="cv-status-pill absolute left-1/2 top-[176px] z-[1000] w-max max-w-[90%] -translate-x-1/2 px-4 py-2.5 text-sm lg:top-[76px]">
            <span x-text="status"></span>
        </div>

        <!-- ============ Notificación flotante (toast) ============ -->
        <div x-show="toast" x-cloak class="absolute left-1/2 top-[226px] z-[1001] -translate-x-1/2 lg:top-24">
            <span class="flex items-center gap-2.5 cv-toast"
                  :class="{ 'cv-toast--success': toast?.type === 'success', 'cv-toast--error': toast?.type === 'error', 'cv-toast--info': !toast || toast?.type === 'info' }">
                <svg x-show="toast?.type === 'success'" x-cloak class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                <svg x-show="toast?.type === 'error'" x-cloak class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"></path></svg>
                <span x-text="toast?.message"></span>
            </span>
        </div>
    </div>
</x-app-layout>