<?php

namespace App\Http\Controllers;

use App\Enums\RouteProfile;
use App\Services\Maps\CiclorutaService;
use App\Services\Maps\GeocodingService;
use App\Services\Maps\MapService;
use App\Services\Maps\OverpassService;
use App\Services\Routing\BicycleRoutingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MapController extends Controller
{
    public function __construct(
        protected GeocodingService $geocoding,
        protected BicycleRoutingService $routing,
        protected MapService $map,
        protected OverpassService $overpass,
        protected CiclorutaService $ciclorutas,
    ) {}

    /**
     * Muestra la página principal (navegación) de CicleVibes.
     */
    public function index(): View
    {
        return view('mapa', [
            'mapConfig' => $this->map->config(),
        ]);
    }

    /**
     * Busca lugares mediante Nominatim.
     */
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'max:150'],
        ]);

        return response()->json([
            'results' => $this->geocoding->search($data['q']),
        ]);
    }

    /**
     * Devuelve la ruta recomendada (perfil de bicicleta).
     */
    public function route(Request $request): JsonResponse
    {
        $data = $this->routeCoordinates($request);

        $route = $this->routing->recommended(
            ['lat' => $data['origin_lat'], 'lng' => $data['origin_lng']],
            ['lat' => $data['dest_lat'], 'lng' => $data['dest_lng']]
        );

        if ($route === null) {
            return response()->json(['error' => 'No se pudo calcular la ruta para bicicleta.'], 422);
        }

        return response()->json(['route' => $route]);
    }

    /**
     * Devuelve varias alternativas de ruta para bicicleta.
     *
     * Con priorize_ciclorutas=1 se antepone una ruta que recorre la red de
     * ciclorrutas (conectando origen/destino con OSRM). Si la red no conecta,
     * se devuelven únicamente las rutas directas de OSRM (fallback).
     */
    public function alternatives(Request $request): JsonResponse
    {
        $data = $request->validate([
            'origin_lat' => ['required', 'numeric', 'between:-90,90'],
            'origin_lng' => ['required', 'numeric', 'between:-180,180'],
            'dest_lat' => ['required', 'numeric', 'between:-90,90'],
            'dest_lng' => ['required', 'numeric', 'between:-180,180'],
            'count' => ['nullable', 'integer', 'min:1', 'max:5'],
            'priorize_ciclorutas' => ['nullable', 'boolean'],
        ]);

        $origin = ['lat' => $data['origin_lat'], 'lng' => $data['origin_lng']];
        $destination = ['lat' => $data['dest_lat'], 'lng' => $data['dest_lng']];
        $count = isset($data['count']) ? (int) $data['count'] : null;
        $priority = filter_var($data['priorize_ciclorutas'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $routes = $priority
            ? $this->routing->routesViaCiclorutas($origin, $destination, $count)
            : $this->routing->routes($origin, $destination, $count);

        if (empty($routes)) {
            return response()->json(['error' => 'No se pudieron calcular rutas para bicicleta.'], 422);
        }

        // ¿Existe una conexión ciclista completa? Solo si el planificador fue
        // capaz de anteponer una ruta que recorre la red de ciclorrutas.
        $hasCiclorutaRoute = $priority && $this->routing->hasViaCiclorutas($routes);

        if ($priority && ! $hasCiclorutaRoute) {
            // Fallback honesto: sin conexión ciclista completa, OSRM se
            // presenta como alternativa, no como ruta por ciclorrutas.
            $routes[0]['label'] = '🛣️ Ruta alternativa';
        }

        return response()->json([
            'routes' => $routes,
            'priorize_ciclorutas' => $priority,
            'cicloruta_connection' => $hasCiclorutaRoute,
        ]);
    }

    /**
     * Calcula las rutas inteligentes por perfil (fase 1).
     *
     * Devuelve una ruta por perfil solicitado (fastest, shortest, easiest,
     * scenic, safest), elegidas sobre el mismo pool de candidatas reales.
     * Cada perfil puede venir sin ruta (null) si no hay datos reales que lo
     * sustenten en este momento; no se inventa información.
     */
    public function profiles(Request $request): JsonResponse
    {
        $data = $request->validate([
            'origin_lat' => ['required', 'numeric', 'between:-90,90'],
            'origin_lng' => ['required', 'numeric', 'between:-180,180'],
            'dest_lat' => ['required', 'numeric', 'between:-90,90'],
            'dest_lng' => ['required', 'numeric', 'between:-180,180'],
            'count' => ['nullable', 'integer', 'min:1', 'max:5'],
            'priorize_ciclorutas' => ['nullable', 'boolean'],
            'profiles' => ['nullable', 'string', 'max:120'],
        ]);

        $origin = ['lat' => $data['origin_lat'], 'lng' => $data['origin_lng']];
        $destination = ['lat' => $data['dest_lat'], 'lng' => $data['dest_lng']];
        $count = isset($data['count']) ? (int) $data['count'] : null;
        $priority = filter_var($data['priorize_ciclorutas'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $configured = (array) config('services.map.profiles', []);
        $requested = $data['profiles'] ?? null;

        if ($requested !== null) {
            $requestedKeys = array_values(array_filter(
                array_map('trim', explode(',', $requested)),
                fn (string $key) => $key !== ''
            ));
            // Solo se admiten perfiles válidos; si vienen vacíos o inválidos,
            // se usa la configuración.
            $profiles = collect($requestedKeys)
                ->filter(fn (string $key) => RouteProfile::tryFromMixed($key) !== null)
                ->values()
                ->all();

            if ($profiles === []) {
                $profiles = $configured;
            }
        } else {
            $profiles = $configured;
        }

        if ($profiles === []) {
            $profiles = RouteProfile::allowedKeys();
        }

        $results = $this->routing->profiles($origin, $destination, $profiles, $count, $priority);

        if (empty($results) || collect($results)->every(fn ($entry) => $entry['route'] === null)) {
            return response()->json(['error' => 'No se pudieron calcular rutas para bicicleta.'], 422);
        }

        return response()->json([
            'profiles' => $results,
            'profiles_requested' => collect($results)->pluck('profile')->values()->all(),
            'priorize_ciclorutas' => $priority,
            'query' => [
                'origin' => $origin,
                'destination' => $destination,
            ],
        ]);
    }

    /**
     * Recalcula la ruta desde la posición actual del ciclista hasta el destino.
     * Se usa durante la navegación cuando el usuario se desvía de la ruta.
     *
     * Acepta 'profile' (opcional) para recalcular conservando el perfil
     * seleccionado; sin él, mantiene el comportamiento anterior (ruta
     * recomendada del motor).
     */
    public function recalculate(Request $request): JsonResponse
    {
        $data = $this->routeCoordinates($request);

        $profile = $request->validate([
            'profile' => ['nullable', 'string', 'max:40'],
        ])['profile'] ?? null;

        $priorize = filter_var(
            $request->input('priorize_ciclorutas', false),
            FILTER_VALIDATE_BOOLEAN
        );

        if ($profile !== null && RouteProfile::tryFromMixed($profile) !== null) {
            $route = $this->routing->routesForProfile(
                ['lat' => $data['origin_lat'], 'lng' => $data['origin_lng']],
                ['lat' => $data['dest_lat'], 'lng' => $data['dest_lng']],
                RouteProfile::from($profile),
                null,
                $priorize,
            );
        } else {
            $route = $this->routing->recommended(
                ['lat' => $data['origin_lat'], 'lng' => $data['origin_lng']],
                ['lat' => $data['dest_lat'], 'lng' => $data['dest_lng']]
            );
        }

        if ($route === null) {
            return response()->json(['error' => 'No se pudo recalcular la ruta.'], 422);
        }

        return response()->json(['route' => $route]);
    }

    /**
     * Devuelve la infraestructura ciclista (ciclorutas) de un área como GeoJSON.
     */
    public function cyclorutas(Request $request): JsonResponse
    {
        $data = $request->validate([
            'min_lat' => ['required', 'numeric', 'between:-90,90'],
            'min_lng' => ['required', 'numeric', 'between:-180,180'],
            'max_lat' => ['required', 'numeric', 'between:-90,90'],
            'max_lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        if ($data['min_lat'] >= $data['max_lat'] || $data['min_lng'] >= $data['max_lng']) {
            return response()->json(['error' => 'Bounding box inválido.'], 422);
        }

        return response()->json(
            $this->overpass->cyclorutas(
                (float) $data['min_lat'],
                (float) $data['min_lng'],
                (float) $data['max_lat'],
                (float) $data['max_lng']
            )
        );
    }

    /**
     * Devuelve la red completa de ciclorrutas de Barranquilla como GeoJSON.
     *
     * Es la red local (resources/data/ciclorutas-barranquilla.geojson) usada
     * por la capa "Ciclorutas" y por el planificador de rutas priorizadas.
     */
    public function ciclorutas(): JsonResponse
    {
        return response()->json($this->ciclorutas->network());
    }

    /**
     * Devuelve la ciclorruta más cercana a unas coordenadas.
     */
    public function ciclorutasNearest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $nearest = $this->ciclorutas->nearest(
            (float) $data['lat'],
            (float) $data['lng']
        );

        if ($nearest === null) {
            return response()->json(['error' => 'No hay infraestructura ciclista registrada cerca.'], 404);
        }

        return response()->json(['nearest' => $nearest]);
    }

    /**
     * Devuelve los marcadores de bicicletas disponibles.
     */
    public function bicicletas(): JsonResponse
    {
        return response()->json([
            'bicicletas' => $this->map->bicicletasMarkers(),
        ]);
    }

    /**
     * Validación común de coordenadas de ruta.
     *
     * @return array<string, float>
     */
    protected function routeCoordinates(Request $request): array
    {
        return $request->validate([
            'origin_lat' => ['required', 'numeric', 'between:-90,90'],
            'origin_lng' => ['required', 'numeric', 'between:-180,180'],
            'dest_lat' => ['required', 'numeric', 'between:-90,90'],
            'dest_lng' => ['required', 'numeric', 'between:-180,180'],
        ]);
    }
}
