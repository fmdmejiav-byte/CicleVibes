<x-app-layout>
    <x-slot name="header">
        <div>
            <span class="text-xs font-bold uppercase tracking-widest text-emerald-600">Mapa</span>
            <h2 class="mt-1 text-2xl font-black tracking-tight text-gray-900 sm:text-3xl">
                Planifica y navega <span class="text-emerald-600">al ritmo de tu bici</span>
            </h2>
            <p class="mt-1.5 text-sm text-gray-600">Encuentra rutas seguras, aprovecha las ciclorrutas y llega a tu destino rodando tranquilo.</p>
        </div>
    </x-slot>

    <div class="relative overflow-hidden border-t border-emerald-100/60"
         x-data="CicleMap({{ json_encode($mapConfig) }})">

        <!-- Mapa -->
        <div id="cicle-map" class="map-container-lg w-full bg-gray-100"></div>

        <!-- Buscador de destino (superior, centrado) -->
        <div class="absolute left-3 top-3 right-3 z-[1000] flex justify-center">
            <div class="w-full max-w-xl">
                <div class="flex items-center overflow-hidden rounded-full bg-white shadow-lg ring-1 ring-gray-200 focus-within:ring-2 focus-within:ring-emerald-500">
                    <span class="flex items-center pl-4 text-emerald-600">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                    </span>
                    <input
                        type="text"
                        x-model="query"
                        @input="triggerSearch()"
                        placeholder="¿A dónde quieres ir? (ej. Universidad del Norte)"
                        class="w-full border-0 bg-transparent px-3 py-3.5 text-sm text-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-0"
                    >
                    <button
                        x-show="query.length > 0"
                        @click="query = ''; results = []; selectedPlace = null; status = ''"
                        class="flex items-center pr-4 text-gray-400 hover:text-gray-600"
                    >
                        <span x-cloak class="text-lg leading-none">&times;</span>
                    </button>
                </div>

                <!-- Resultados de búsqueda -->
                <div x-show="searching" x-cloak class="mt-1.5 rounded-2xl bg-white px-4 py-3 text-sm text-gray-500 shadow-lg ring-1 ring-gray-200">
                    Buscando…
                </div>
                <div x-show="results.length > 0" x-cloak class="mt-1.5 max-h-72 overflow-y-auto rounded-2xl bg-white shadow-lg ring-1 ring-gray-200">
                    <template x-for="(r, i) in results" :key="i">
                        <button @click="selectResult(r)"
                            class="flex w-full items-start gap-3 border-b border-gray-100 px-4 py-3 text-left transition hover:bg-emerald-50 last:border-0">
                            <span class="mt-0.5 flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate font-semibold text-gray-800" x-text="r.name"></span>
                                <span class="block truncate text-xs text-gray-500" x-text="r.address"></span>
                            </span>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <!-- Controles de capa (derecha) -->
        <div class="absolute right-3 top-20 z-[1000] flex flex-col items-end gap-2 lg:top-16">
            <button @click="setOriginFromUser()" :title="locating ? 'Localizando…' : 'Mi ubicación'"
                class="flex h-11 w-11 items-center justify-center rounded-full bg-white text-emerald-600 shadow-lg ring-1 ring-gray-200 transition hover:bg-emerald-50"
                :class="{ 'animate-pulse': locating }">
                <svg x-show="!locating" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M12 2v2M12 20v2M2 12h2M20 12h2"></path></svg>
                <svg x-show="locating" x-cloak class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity="0.25"></circle><path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path></svg>
            </button>

            <button @click="toggleBicicletas()" :title="showBicicletas ? 'Ocultar bicicletas' : 'Mostrar bicicletas'"
                class="flex h-11 w-11 items-center justify-center rounded-full bg-white text-emerald-600 shadow-lg ring-1 ring-gray-200 transition hover:bg-emerald-50"
                :class="showBicicletas ? 'ring-emerald-500' : 'opacity-70'">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="18.5" cy="17.5" r="3.5"></circle><path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"></path></svg>
            </button>

            <button @click="toggleCyclorutas()" :title="showCyclorutas ? 'Ocultar ciclorutas' : 'Mostrar ciclorutas'"
                class="flex h-11 items-center gap-2 rounded-full bg-white pl-3 pr-4 text-emerald-600 shadow-lg ring-1 ring-gray-200 transition hover:bg-emerald-50"
                :class="showCyclorutas ? 'ring-emerald-500' : 'opacity-70'">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 1.82.33z"></path><path d="M4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06A2 2 0 1 1 7 9.95l-.06.06A1.65 1.65 0 0 0 4.6 9z"></path><path d="m14 5 2 -0.5"></path></svg>
                <span class="pr-1 text-xs font-bold text-emerald-700" x-text="showCyclorutas ? 'Ciclorutas' : 'Ciclorutas'"></span>
            </button>
        </div>

        <!-- Panel de planificación / rutas / navegación -->
        <div x-cloak class="absolute bottom-3 left-1/2 z-[1000] flex max-h-[45vh] w-[calc(100%-1.5rem)] max-w-xl -translate-x-1/2 flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-gray-200 lg:left-4 lg:right-auto lg:top-16 lg:bottom-3 lg:w-96 lg:max-w-none lg:translate-x-0 lg:max-h-[calc(100vh-16rem)]">
            <div class="flex flex-col overflow-hidden">
                <template x-if="mode === 'plan' || mode === 'routes' || mode === 'pick'">
                    <div class="flex flex-col overflow-hidden">
                        <div class="border-b border-emerald-100/70 bg-emerald-50/40 px-4 py-3">
                            <h3 class="font-black tracking-tight text-gray-900" x-text="mode === 'routes' ? 'Elige tu ruta' : 'Planificar ruta'"></h3>
                        </div>

                        <div class="flex-1 space-y-3 overflow-y-auto p-4">

                            <!-- Lugar seleccionado de la búsqueda -->
                            <div x-show="selectedPlace" x-cloak class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Lugar seleccionado</p>
                                <p class="mt-0.5 truncate font-semibold text-gray-800" x-text="selectedPlace?.name"></p>
                                <p class="line-clamp-2 text-xs text-gray-500" x-text="selectedPlace?.address"></p>
                                <div class="mt-3 flex gap-2">
                                    <button @click="setAsOriginFromSearch()" class="flex-1 rounded-lg bg-emerald-600 px-2 py-1.5 text-xs font-bold text-white transition hover:bg-emerald-700">Usar como origen</button>
                                    <button @click="setAsDestinationFromSearch()" class="flex-1 rounded-lg bg-red-500 px-2 py-1.5 text-xs font-bold text-white transition hover:bg-red-600">Como destino</button>
                                </div>
                            </div>

                            <!-- Priorizar ciclorrutas -->
                            <label class="flex cursor-pointer items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50/60 p-3 transition hover:bg-emerald-50">
                                <span class="flex items-center gap-2 text-sm font-bold text-emerald-700">
                                    <span>🚲 Priorizar ciclorrutas</span>
                                </span>
                                <input
                                    type="checkbox"
                                    :checked="priorizarCiclorutas"
                                    @change="togglePriorizarCiclorutas()"
                                    class="h-5 w-5 cursor-pointer rounded accent-emerald-600"
                                >
                            </label>

                            <!-- Help de clic en el mapa -->
                            <div x-show="pickHint" x-cloak class="rounded-xl bg-emerald-50 px-4 py-3 text-center text-sm font-semibold text-emerald-700">
                                <span x-text="pickHint"></span>
                            </div>

                            <!-- Origen -->
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50/40 p-3">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Origen</p>
                                    <div class="flex gap-1.5">
                                        <button @click="setOriginFromUser()" class="rounded-md bg-emerald-600 px-2 py-1 text-[11px] font-bold text-white hover:bg-emerald-700">📍 Mi ubicación</button>
                                        <button @click="pickOnMap('origin')" class="rounded-md border border-emerald-300 bg-white px-2 py-1 text-[11px] font-bold text-emerald-700 hover:bg-emerald-50">Señalar en mapa</button>
                                    </div>
                                </div>
                                <p class="mt-1 truncate text-sm font-medium text-gray-800" x-text="originLabel(origin)"></p>
                            </div>

                            <!-- Destino -->
                            <div class="rounded-xl border border-red-200 bg-red-50/40 p-3">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-red-500">Destino</p>
                                    <button @click="pickOnMap('destination')" class="rounded-md border border-red-300 bg-white px-2 py-1 text-[11px] font-bold text-red-600 hover:bg-red-50">Señalar en mapa</button>
                                </div>
                                <p class="mt-1 truncate text-sm font-medium text-gray-800" x-text="destinationLabel(destination)"></p>
                            </div>

                            <div class="flex gap-2" x-show="origin && destination" x-cloak>
                                <button @click="reverseOriginDestination()" class="flex-1 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-600 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700">⇄ Intercambiar</button>
                                <button @click="clearRoute()" class="flex-1 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-600 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700">Limpiar</button>
                            </div>

                            <!-- Alternativas -->
                            <div x-show="mode === 'routes' && alternatives.length > 0" x-cloak class="space-y-2">
                                <div x-show="priorizarCiclorutas && !ciclorutaConnection" x-cloak class="rounded-xl border border-amber-200 bg-amber-50 p-3">
                                    <p class="text-sm font-bold text-amber-800">🚲 No existe una conexión ciclista completa con los datos disponibles.</p>
                                    <p class="mt-0.5 text-xs text-amber-700">Mostramos la alternativa por calles (OSRM) para que puedas llegar igualmente.</p>
                                </div>
                                <template x-for="r in alternatives" :key="r.id">
                                    <button @click="selectAlternative(r.id)"
                                        class="flex w-full items-center gap-3 rounded-xl border p-3 text-left transition"
                                        :class="selectedRouteId === r.id ? 'border-emerald-500 bg-emerald-50 ring-1 ring-emerald-500' : 'border-gray-200 bg-white hover:bg-emerald-50/40'">
                                        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl"
                                            :class="selectedRouteId === r.id ? 'bg-emerald-600 text-white' : 'bg-emerald-100 text-emerald-600'">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 19V9a2 2 0 0 1 2-2h2"></path><circle cx="14" cy="8" r="3"></circle><path d="M14 8v6a2 2 0 0 1-2 2H6"></path></svg>
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="flex items-center justify-between">
                                                <span class="font-bold text-gray-800" x-text="r.label"></span>
                                                <span class="text-xs font-semibold" x-text="r.via_ciclorutas ? 'Ciclorrutas + OSRM' : (r.driver === 'graphhopper' ? 'GraphHopper' : 'OSRM')"></span>
                                            </span>
                                            <span class="mt-0.5 flex items-center gap-3 text-sm text-gray-600">
                                                <span class="font-semibold" x-text="formatKm(r.distance_km)"></span>
                                                <span>·</span>
                                                <span x-text="formatMin(r.duration_min)"></span>
                                            </span>
                                            <span x-show="r.cicloruta_coverage_pct > 0" x-cloak class="mt-1.5 block space-y-1">
                                                <span class="flex items-center gap-1 text-xs font-semibold text-emerald-700">
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="18.5" cy="17.5" r="3.5"></circle><path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"></path></svg>
                                                    <span x-text="r.cicloruta_coverage_pct + '% del trayecto por ciclorruta'"></span>
                                                </span>
                                                <span x-show="r.ciclorutas_used && r.ciclorutas_used.length" class="block text-xs text-gray-500">
                                                    <span class="font-semibold text-gray-600">Ciclorrutas utilizadas:</span>
                                                    <span x-text="r.ciclorutas_used.join(' · ')"></span>
                                                </span>
                                            </span>
                                        </span>
                                        <span x-show="selectedRouteId === r.id" x-cloak class="text-emerald-600">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                                        </span>
                                    </button>
                                </template>
                            </div>

                            <!-- Botón Calcular / Iniciar -->
                            <div class="pt-1">
                                <template x-if="mode === 'routes' && alternatives.length > 0">
                                    <button @click="startNavigation()"
                                        class="flex w-full items-center justify-center gap-2 rounded-xl nav-route px-4 py-3.5 text-sm font-bold text-white shadow-lg transition hover:opacity-95">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"></path></svg>
                                        INICIAR RUTA
                                    </button>
                                </template>
                                <template x-if="mode !== 'routes'">
                                    <button @click="calculateAlternatives()"
                                        :disabled="!origin || !destination || routing"
                                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3.5 text-sm font-bold text-white shadow-lg shadow-emerald-600/25 transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">
                                        <span x-show="!routing" class="inline-flex items-center gap-2">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="19" r="3"></circle><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"></path><circle cx="18" cy="5" r="3"></circle></svg>
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
                    <div class="flex flex-col">
                        <!-- Desviación -->
                        <div x-show="deviating" x-cloak class="nav-danger px-4 py-3 text-white">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-bold">Te has desviado de la ruta</p>
                                    <p class="text-xs text-white/80">Tu posición se alejó del camino calculado.</p>
                                </div>
                                <button @click="confirmRecalculate()"
                                    class="rounded-lg bg-white px-3 py-2 text-xs font-bold text-red-600 shadow hover:bg-red-50"
                                    :disabled="routing">
                                    <span x-show="!routing">Recalcular</span>
                                    <span x-show="routing" x-cloak>…</span>
                                </button>
                            </div>
                        </div>

                        <!-- HUD principal -->
                        <div class="nav-route px-4 pt-3 pb-4 text-white">
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-bold uppercase tracking-wide text-emerald-100">Navegando</p>
                                <span class="rounded-full bg-white/15 px-2 py-0.5 text-[10px] font-bold uppercase">Perfil bici</span>
                            </div>

                            <!-- Instrucción actual -->
                            <p class="mt-3 text-lg font-bold leading-snug" x-text="routeInfo && routeInfo.steps && routeInfo.steps[currentStep] ? routeInfo.steps[currentStep].instruction : 'Gira según las indicaciones del mapa.'"></p>

                            <div class="mt-3 flex items-center justify-around">
                                <div class="text-center">
                                    <p class="text-2xl font-extrabold" x-text="formatKm(distanceRemainingKm)"></p>
                                    <p class="text-xs text-emerald-100">Restante</p>
                                </div>
                                <div class="h-9 w-px bg-white/25"></div>
                                <div class="text-center">
                                    <p class="text-2xl font-extrabold" x-text="formatMin(timeRemainingMin)"></p>
                                    <p class="text-xs text-emerald-100">Tiempo estimado</p>
                                </div>
                                <div class="h-9 w-px bg-white/25"></div>
                                <div class="text-center">
                                    <p class="text-2xl font-extrabold" x-text="(routeInfo?.steps?.length ?? 0)"></p>
                                    <p class="text-xs text-emerald-100">Pasos</p>
                                </div>
                            </div>
                        </div>

                        <!-- Acciones de navegación -->
                        <div class="flex gap-2 p-3">
                            <button @click="stopNavigation()"
                                class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-red-500 px-4 py-3 text-sm font-bold text-white transition hover:bg-red-600">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="6" width="12" height="12" rx="2"></rect></svg>
                                Detener
                            </button>
                        </div>
                    </div>
                </template>

                <!-- Instrucciones detalladas (colapsables al iniciar) -->
                <div x-show="routeInfo && routeInfo.steps && routeInfo.steps.length" x-cloak
                     class="max-h-full overflow-y-auto border-t border-gray-100">
                    <div class="px-4 py-2 text-xs font-bold uppercase tracking-wide text-gray-400">Instrucciones</div>
                    <template x-for="(s, i) in (routeInfo?.steps ?? [])" :key="i">
                        <div class="flex items-start gap-3 border-b border-gray-50 px-4 py-2 last:border-0"
                             :class="i === currentStep && mode === 'navigating' ? 'bg-emerald-50' : ''">
                            <span class="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full text-[10px] font-bold"
                                :class="i === currentStep && mode === 'navigating' ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-500'"
                                x-text="i + 1"></span>
                            <span class="min-w-0 flex-1 text-sm text-gray-700" x-text="s.instruction"></span>
                            <span class="text-xs font-semibold text-gray-400" x-text="s.distance_m > 0 ? Math.round(s.distance_m) + ' m' : ''"></span>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Leyenda de ciclorrutas -->
        <div x-show="showCyclorutas" x-cloak class="absolute left-3 top-40 z-[999] rounded-2xl bg-white/95 px-3 py-2.5 text-xs shadow-lg ring-1 ring-gray-200 backdrop-blur lg:left-auto lg:right-3 lg:top-44">
            <p class="mb-1.5 text-[11px] font-bold uppercase tracking-wide text-gray-700">Ciclorrutas</p>
            <div class="space-y-1">
                <div class="flex items-center gap-2"><span>🔴</span><span class="font-medium text-gray-700">Ciclorruta en calzada</span></div>
                <div class="flex items-center gap-2"><span>🟠</span><span class="font-medium text-gray-700">Ciclorruta en andén</span></div>
                <div class="flex items-center gap-2"><span>🟡</span><span class="font-medium text-gray-700">Ciclobanda</span></div>
                <div class="flex items-center gap-2"><span>🔵</span><span class="font-medium text-gray-700">Carril ciclo preferente</span></div>
            </div>
        </div>

        <!-- Estado / barra de información -->
        <div x-show="status" x-cloak class="absolute left-1/2 top-20 z-[999] w-max max-w-[90%] -translate-x-1/2 rounded-xl bg-gray-900/90 px-4 py-2.5 text-sm text-white shadow-lg backdrop-blur">
            <span x-text="status"></span>
        </div>

        <!-- Notificación flotante -->
        <div x-show="toast" x-cloak class="absolute left-1/2 top-28 z-[1001] -translate-x-1/2"
             :class="{ 'bg-emerald-600': toast?.type === 'success', 'bg-red-600': toast?.type === 'error', 'bg-slate-800': !toast || toast?.type === 'info' }">
            <span class="flex items-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold text-white shadow-lg">
                <span x-text="toast?.message"></span>
            </span>
        </div>
    </div>
</x-app-layout>
