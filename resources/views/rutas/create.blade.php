<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Planifica tu ruta — {{ config('app.name', 'CicleVibes') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/ruta-planner.js'])
        @endif
    </head>

    <body class="font-sans antialiased">
        <div
            id="ruta-planner"
            data-map-key="{{ config('services.google_maps.key') }}"
            class="flex h-screen flex-col bg-gray-100 md:flex-row">

            <aside class="order-2 h-1/2 w-full shrink-0 overflow-y-auto md:order-1 md:h-full md:w-[360px] lg:w-[380px]">
                <div class="m-3 flex min-h-full flex-col gap-4 rounded-2xl bg-white p-4 shadow-lg md:m-4 md:p-5">

                    <div class="flex items-center justify-between">
                        <a href="{{ route('rutas.index') }}" class="inline-flex items-center gap-1 text-xs font-medium text-gray-500 transition hover:text-green-600">
                            <x-icon name="arrow-right" class="h-3.5 w-3.5 rotate-180" />
                            Mis rutas
                        </a>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-600 text-white shadow-md">
                            <x-icon name="map" class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-green-700">CicleVibes</p>
                            <h1 class="text-lg font-bold leading-tight text-gray-900">Planifica tu ruta</h1>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="relative">
                            <x-icon name="map-pin" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-green-600" />
                            <input
                                id="origen-input"
                                type="text"
                                placeholder="Lugar de inicio"
                                autocomplete="off"
                                value="{{ old('origen') }}"
                                class="w-full rounded-xl border-0 bg-gray-50 py-2.5 pl-9 pr-3 text-sm text-gray-800 shadow-sm ring-1 ring-inset ring-gray-200 transition placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-green-500" />
                        </div>

                        <div class="relative">
                            <div class="absolute right-2 top-1/2 z-10 -translate-y-1/2">
                                <button
                                    id="btn-invertir"
                                    type="button"
                                    title="Invertir origen y destino"
                                    class="flex h-7 w-7 items-center justify-center rounded-full bg-white text-gray-500 shadow ring-1 ring-gray-200 transition hover:text-green-600">
                                    <x-icon name="arrows-right-left" class="h-4 w-4" />
                                </button>
                            </div>
                            <x-icon name="map-pin" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-red-500" />
                            <input
                                id="destino-input"
                                type="text"
                                placeholder="Lugar de destino"
                                autocomplete="off"
                                value="{{ old('destino') }}"
                                class="w-full rounded-xl border-0 bg-gray-50 py-2.5 pl-9 pr-10 text-sm text-gray-800 shadow-sm ring-1 ring-inset ring-gray-200 transition placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-green-500" />
                        </div>
                    </div>

                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Tipo de ruta</p>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="cursor-pointer">
                                <input type="radio" name="tipo-ruta" value="segura" checked class="peer sr-only" />
                                <span class="flex items-center gap-2 rounded-xl border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 transition peer-checked:border-green-500 peer-checked:bg-green-50 peer-checked:text-green-800">
                                    <x-icon name="shield-check" class="h-4 w-4 shrink-0" />
                                    Ruta segura para bicicletas
                                </span>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="tipo-ruta" value="rapida" class="peer sr-only" />
                                <span class="flex items-center gap-2 rounded-xl border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 transition peer-checked:border-green-500 peer-checked:bg-green-50 peer-checked:text-green-800">
                                    <x-icon name="arrow-trending-up" class="h-4 w-4 shrink-0" />
                                    Ruta más rápida
                                </span>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="tipo-ruta" value="trafico" class="peer sr-only" />
                                <span class="flex items-center gap-2 rounded-xl border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 transition peer-checked:border-green-500 peer-checked:bg-green-50 peer-checked:text-green-800">
                                    <x-icon name="arrow-path" class="h-4 w-4 shrink-0" />
                                    Evitar tráfico
                                </span>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="tipo-ruta" value="pendientes" class="peer sr-only" />
                                <span class="flex items-center gap-2 rounded-xl border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 transition peer-checked:border-green-500 peer-checked:bg-green-50 peer-checked:text-green-800">
                                    <x-icon name="map-pin" class="h-4 w-4 shrink-0" />
                                    Evitar pendientes
                                </span>
                            </label>
                        </div>
                    </div>

                    <button
                        id="btn-buscar"
                        type="button"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-green-600 px-4 py-3 text-sm font-bold text-white shadow-md transition hover:bg-green-700 active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60">
                        <x-icon name="magnifying-glass" class="h-4 w-4" />
                        Buscar ruta
                    </button>

                    <p id="estado-ruta" class="min-h-4 text-center text-xs text-gray-500"></p>

                    <div id="resumen" class="hidden space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Resumen</p>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="rounded-xl bg-gray-50 p-3 ring-1 ring-inset ring-gray-100">
                                <div class="flex items-center gap-1.5 text-gray-400">
                                    <x-icon name="arrow-trending-up" class="h-3.5 w-3.5" />
                                    <span class="text-[10px] font-semibold uppercase">Distancia</span>
                                </div>
                                <p id="resumen-distancia" class="mt-1 text-sm font-bold text-gray-900">—</p>
                            </div>
                            <div class="rounded-xl bg-gray-50 p-3 ring-1 ring-inset ring-gray-100">
                                <div class="flex items-center gap-1.5 text-gray-400">
                                    <x-icon name="clock" class="h-3.5 w-3.5" />
                                    <span class="text-[10px] font-semibold uppercase">Tiempo estimado</span>
                                </div>
                                <p id="resumen-tiempo" class="mt-1 text-sm font-bold text-gray-900">—</p>
                            </div>
                            <div class="rounded-xl bg-gray-50 p-3 ring-1 ring-inset ring-gray-100">
                                <div class="flex items-center gap-1.5 text-gray-400">
                                    <x-icon name="shield-check" class="h-3.5 w-3.5" />
                                    <span class="text-[10px] font-semibold uppercase">Nivel de seguridad</span>
                                </div>
                                <p id="resumen-seguridad" class="mt-1 text-sm font-bold text-gray-900">—</p>
                            </div>
                            <div class="rounded-xl bg-gray-50 p-3 ring-1 ring-inset ring-gray-100">
                                <div class="flex items-center gap-1.5 text-gray-400">
                                    <x-icon name="map-pin" class="h-3.5 w-3.5" />
                                    <span class="text-[10px] font-semibold uppercase">Uso de infraestructura</span>
                                </div>
                                <p id="resumen-infra" class="mt-1 text-sm font-bold text-gray-900">—</p>
                            </div>
                        </div>
                    </div>

                    <p id="mensaje-recomendacion" class="hidden rounded-xl px-3 py-2.5 text-xs font-medium"></p>

                    <button
                        id="btn-indicaciones"
                        type="button"
                        class="hidden items-center justify-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                        <x-icon name="list-bullet" class="h-4 w-4" />
                        Ver indicaciones paso a paso
                    </button>

                    <div id="panel-indicaciones" class="hidden max-h-60 overflow-y-auto rounded-xl bg-gray-50 p-3 ring-1 ring-inset ring-gray-100">
                        <ol id="contenedor-indicaciones" class="list-none"></ol>
                    </div>

                    <div class="rounded-xl bg-gray-50 p-3 ring-1 ring-inset ring-gray-100">
                        <label class="flex cursor-pointer items-center justify-between">
                            <span class="text-xs font-semibold text-gray-700">Ciclorrutas de Barranquilla</span>
                            <input id="mostrar-ciclorrutas" type="checkbox" checked class="h-4 w-4 rounded border-gray-300 text-green-600 focus:ring-green-500" />
                        </label>
                        <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1.5">
                            <span class="inline-flex items-center gap-1 text-[10px] font-medium text-gray-500">
                                <span class="h-2 w-4 rounded-full" style="background: #E23B2E;"></span>
                                Ciclorruta en calzada
                            </span>
                            <span class="inline-flex items-center gap-1 text-[10px] font-medium text-gray-500">
                                <span class="h-2 w-4 rounded-full" style="background: #FF9500;"></span>
                                Ciclorruta en andén
                            </span>
                            <span class="inline-flex items-center gap-1 text-[10px] font-medium text-gray-500">
                                <span class="h-2 w-4 rounded-full" style="background: #FFD400;"></span>
                                Ciclobanda
                            </span>
                            <span class="inline-flex items-center gap-1 text-[10px] font-medium text-gray-500">
                                <span class="h-2 w-4 rounded-full" style="background: #1E88E5;"></span>
                                Carril preferente
                            </span>
                        </div>
                    </div>

                    <form
                        id="form-ruta"
                        action="{{ route('rutas.store') }}"
                        method="POST"
                        class="mt-1 border-t border-gray-100 pt-4">
                        @csrf

                        <input id="input-nombre" type="hidden" name="nombre" value="{{ old('nombre') }}">
                        <input id="input-origen" type="hidden" name="origen" value="{{ old('origen') }}">
                        <input id="input-destino" type="hidden" name="destino" value="{{ old('destino') }}">
                        <input id="input-distancia" type="hidden" name="distancia" value="{{ old('distancia') }}">
                        <input id="input-duracion" type="hidden" name="duracion" value="{{ old('duracion') }}">
                        <input id="input-polilinea" type="hidden" name="polilinea" value="{{ old('polilinea') }}">

                        @error('nombre')
                            <p class="mb-2 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                        @error('origen')
                            <p class="mb-2 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                        @error('destino')
                            <p class="mb-2 text-xs text-red-600">{{ $message }}</p>
                        @enderror

                        <button
                            id="btn-guardar"
                            type="submit"
                            disabled
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-gray-900 px-4 py-3 text-sm font-bold text-white shadow transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-40">
                            <x-icon name="check-circle" class="h-4 w-4" />
                            Guardar ruta
                        </button>
                    </form>

                </div>
            </aside>

            <div class="order-1 relative h-1/2 w-full md:order-2 md:h-full md:flex-1">
                <div id="map" class="h-full w-full"></div>
                <div id="leyenda-ciclorrutas" class="pointer-events-none absolute bottom-4 left-4 z-10 rounded-xl bg-white/95 px-3 py-2 shadow-lg ring-1 ring-gray-200 backdrop-blur"></div>
            </div>
        </div>
    </body>
</html>
