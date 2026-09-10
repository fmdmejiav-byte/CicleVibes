<?php

namespace App\Services\Routing;

use App\Contracts\SafetyScoringService;
use App\Enums\RouteProfile;
use App\Services\Maps\CiclorutaService;
use App\Services\Routing\Safety\InfrastructureSafetyScoringService;

/**
 * Servicio de enrutamiento para bicicletas.
 *
 * Normaliza la respuesta de cualquier driver (OSRM, GraphHopper, ...) a una
 * estructura única para la aplicación y añade metadatos de presentación
 * (etiqueta de la alternativa, puntuación de ciclorrutas, etc.).
 *
 * Además integra la red de ciclorrutas de CicleVibes (CiclorutaService):
 * cuando se activa "Priorizar ciclorrutas" se ejecuta una ponderación real:
 *   1. todas las candidatas (incluidas las directas de OSRM) se puntúan por
 *      cuánta infraestructura ciclista recorren (CiclorutaService::routeInfraStats);
 *   2. se construye una ruta que recorre la red: si ambos accesos están en el
 *      mismo componente, se conecta origen → acceso → red → acceso → destino;
 *      si están en componentes distintos, se encadena la red de ambos lados
 *      con un conector OSRM acotado (bike_route_max_gap_m);
 *   3. todas las candidatas se ordenan por un coste ponderado que abarata el
 *      tiempo según el índice de seguridad de infraestructura
 *      (bike_route_cycle_pref), de modo que una ruta algo más larga pero con
 *      mucha más ciclorruta puede ser la recomendada.
 *
 * Si no hay red viable, se mantiene el comportamiento actual (OSRM directo)
 * como fallback honesto.
 */
class BicycleRoutingService
{
    protected RouteProfileRanker $ranker;

    protected SafetyScoringService $safety;

    public function __construct(
        protected BicycleRoutingDriver $driver,
        protected CiclorutaService $ciclorutas,
        ?RouteProfileRanker $ranker = null,
        ?SafetyScoringService $safety = null,
    ) {
        // Los parámetros opcionales permiten construir el servicio en tests
        // antiguos con solo (driver, ciclorutas). El contenedor resuelve los
        // bindings cuando no se pasan.
        $this->safety = $safety ?? new InfrastructureSafetyScoringService();
        $this->ranker = $ranker ?? new RouteProfileRanker($this->safety);
    }

    /**
     * Devuelve las rutas alternativas entre origen y destino, ya
     * enriquecidas para su presentación.
     *
     * @return array<int, array<string, mixed>>
     */
    public function routes(array $origin, array $destination, ?int $alternatives = null): array
    {
        $raw = $this->driver->routes($origin, $destination, $alternatives) ?? [];

        $routes = [];
        foreach ($raw as $index => $route) {
            $route = $this->decorate($route, $index);
            $this->normalizeForProfile($route);
            $this->attachBaseMetadata($route);

            $routes[] = $route;
        }

        return $routes;
    }

    /**
     * Igual que routes(), pero cuando es viable antepone (u ordena al frente)
     * una ruta que prioriza la red de ciclorrutas. En cualquier caso puntúa y
     * ordena las candidatas por el coste ponderado por seguridad ciclista.
     *
     * @return array<int, array<string, mixed>>
     */
    public function routesViaCiclorutas(array $origin, array $destination, ?int $alternatives = null): array
    {
        $routes = $this->routes($origin, $destination, $alternatives);

        $directDistance = ! empty($routes)
            ? (float) ($routes[0]['distance_m'] ?? 0)
            : null;

        // Métricas de coincidencia con la red para las rutas directas de OSRM.
        foreach ($routes as &$route) {
            $this->attachCiclorutaMetrics($route);
        }
        unset($route);

        // Candidata priorizada: mismo componente primero; si no, red encadenada
        // entre componentes (conector OSRM acotado).
        $candidate = $this->viaCiclorutas($origin, $destination, $directDistance);
        if ($candidate === null) {
            $candidate = $this->chainedViaCiclorutas($origin, $destination, $directDistance);
        }

        if ($candidate !== null) {
            // La candidata por red no pasa por decorate() de routes(): se
            // normaliza aquí para incluir los campos de elevación/metadata.
            $this->normalizeForProfile($candidate);
            $this->attachBaseMetadata($candidate);
            $routes[] = $candidate;
            $this->sortByCiclorutaPriority($routes);

            // Las rutas OSRM pasan a presentarse como alternativas tras la
            // ruta recomendada por ciclorrutas.
            $labels = ['🛣️ Ruta alternativa', 'Ruta tranquila'];
            $labelIndex = 0;
            foreach ($routes as &$route) {
                if (($route['via_ciclorutas'] ?? false) === true) {
                    continue;
                }
                $route['label'] = $labels[$labelIndex] ?? ('Alternativa '.($labelIndex + 2));
                $labelIndex++;
            }
            unset($route);
        } else {
            // Fallback honesto: no hay conexión ciclista completa, pero aún así
            // las alternativas OSRM se ordenan favoreciendo la que mejor se
            // alinea con la infraestructura (la opción nunca queda "muerta").
            $this->sortByCiclorutaPriority($routes);
        }

        return $routes;
    }

    /**
     * Devuelve la ruta recomendada (la primera) o null si no hay ninguna.
     *
     * @return array<string, mixed>|null
     */
    public function recommended(array $origin, array $destination): ?array
    {
        $routes = $this->routes($origin, $destination, 1);

        return $routes[0] ?? null;
    }

    /**
     * Construye la ruta priorizando ciclorrutas.
     *
     * Devuelve null (y por tanto fallback a OSRM) cuando:
     *  - no hay ciclorruta cercana al origen o al destino;
     *  - la red de ciclorrutas no conecta ambos accesos;
     *  - OSRM falla en algún tramo de acceso;
     *  - la desviación respecto a la ruta directa supera
     *    bike_route_max_detour;
     *  - la cobertura sobre infraestructura ciclista es menor que
     *    bike_route_min_cicloruta_coverage.
     *
     * @param  array<string, float>  $origin  ['lat' =>, 'lng' =>]
     * @param  array<string, float>  $destination  ['lat' =>, 'lng' =>]
     * @return array<string, mixed>|null
     */
    public function viaCiclorutas(array $origin, array $destination, ?float $directDistanceM = null): ?array
    {
        $originLat = (float) $origin['lat'];
        $originLng = (float) $origin['lng'];
        $destLat = (float) $destination['lat'];
        $destLng = (float) $destination['lng'];

        $originAccess = $this->ciclorutas->nearest($originLat, $originLng);
        $destAccess = $this->ciclorutas->nearest($destLat, $destLng);

        if ($originAccess === null || $destAccess === null) {
            return null;
        }

        $networkPath = $this->ciclorutas->pathBetween(
            (float) $originAccess['nearest_lat'],
            (float) $originAccess['nearest_lng'],
            (float) $destAccess['nearest_lat'],
            (float) $destAccess['nearest_lng'],
        );

        // Red no conectada o accesos en el mismo punto: no es viable priorizar.
        if ($networkPath === null || $networkPath['distance_m'] < 20) {
            return null;
        }

        // Tramo 1: origen → acceso a la ciclorruta.
        $originLeg = $this->osrmLeg(
            $origin,
            ['lat' => (float) $originAccess['nearest_lat'], 'lng' => (float) $originAccess['nearest_lng']]
        );

        // Tramo 2: última ciclorruta → destino.
        $destLeg = $this->osrmLeg(
            ['lat' => (float) $destAccess['nearest_lat'], 'lng' => (float) $destAccess['nearest_lng']],
            $destination
        );

        if ($originLeg === null || $destLeg === null) {
            return null;
        }

        // Geometría unida: origen → (OSRM) → acceso1 → (red) → acceso2 → (OSRM) → destino.
        $coordinates = array_merge(
            $originLeg['coordinates'],
            $networkPath['coordinates'],
            $destLeg['coordinates']
        );

        $networkDistance = (float) $networkPath['distance_m'];
        $totalDistance = $originLeg['distance_m'] + $networkDistance + $destLeg['distance_m'];

        // Cobertura: porcentaje del trayecto sobre infraestructura ciclista.
        $coverage = $totalDistance > 0 ? $networkDistance / $totalDistance : 0.0;

        $maxDetour = (float) config('services.map.bike_route_max_detour', 1.5);
        $minCoverage = (float) config('services.map.bike_route_min_cicloruta_coverage', 0.25);

        if ($directDistanceM !== null && $directDistanceM > 0 && $totalDistance > $directDistanceM * $maxDetour) {
            return null;
        }

        if ($coverage < $minCoverage) {
            return null;
        }

        $speedKmh = (float) config('services.map.bike_avg_speed_kmh', 15);
        $networkDuration = $speedKmh > 0 ? $networkDistance / ($speedKmh * 1000 / 3600) : 0.0;

        $totalDuration = $originLeg['duration_seconds'] + $networkDuration + $destLeg['duration_seconds'];

        $used = $networkPath['used'];
        $usedNames = [];
        foreach ($used as $u) {
            if ($u['name'] !== '' && ! in_array($u['name'], $usedNames, true)) {
                $usedNames[] = $u['name'];
            }
        }

        $infraStats = $this->ciclorutas->routeInfraStats($coordinates, (float) $totalDistance);
        $coverage = (int) round($coverage * 100);

        return [
            'id' => 'via-ciclorutas',
            'distance_m' => (float) round($totalDistance, 1),
            'duration_seconds' => (float) round($totalDuration, 1),
            'distance_km' => round($totalDistance / 1000, 2),
            'duration_min' => (int) round($totalDuration / 60),
            'coordinates' => $coordinates,
            'steps' => $this->buildSteps($originLeg, $used, $destLeg),
            'summary' => $usedNames !== [] ? implode(' · ', array_slice($usedNames, 0, 4)) : 'Ruta por ciclorrutas',
            'profile' => 'cicle',
            'driver' => 'ciclorutas',
            'label' => '🚲 Ruta por ciclorrutas',
            'via_ciclorutas' => true,
            'cicloruta_badge' => true,
            'ciclorutas_used' => array_slice($usedNames, 0, 8),
            'cicloruta_coverage_pct' => $coverage,
            'cicloruta_safety_index' => $infraStats['safety_index'],
            'cicloruta_matched_m' => $infraStats['matched_m'],
            'cicloruta_by_type_m' => $infraStats['by_type_m'],
            'bike_network_distance_m' => (float) round($networkDistance, 1),
            'bike_network_used' => array_slice($used, 0, 8),
        ];
    }

    /**
     * Construye una ruta que prioriza la red de ciclorrutas aunque los puntos
     * de acceso estén en componentes distintos de la red.
     *
     * Encadena: origen →(OSRM)→ acceso origen →(red)→ salida del componente
     * origen →(OSRM, conector acotado)→ acceso destino →(red)→ salida destino
     * →(OSRM)→ destino. Es una ruta con conectividad real (cada tramo empieza
     * exactamente donde termina el anterior) y solo se ofrece si el uso de red
     * supera la cobertura mínima y la desviación admitida.
     *
     * Devuelve null (fallback a OSRM) cuando:
     *  - no hay infraestructura cercana a origen o destino;
     *  - ambos accesos están en el mismo componente (lo cubre viaCiclorutas);
     *  - el conector entre componentes supera bike_route_max_gap_m;
     *  - el uso neto de la red es despreciable o no supera la cobertura mínima;
     *  - algún tramo OSRM falla o se supera la desviación permitida.
     *
     * @param  array<string, float>  $origin  ['lat' =>, 'lng' =>]
     * @param  array<string, float>  $destination  ['lat' =>, 'lng' =>]
     * @return array<string, mixed>|null
     */
    public function chainedViaCiclorutas(array $origin, array $destination, ?float $directDistanceM = null): ?array
    {
        $originAccess = $this->ciclorutas->nearest(
            (float) $origin['lat'],
            (float) $origin['lng']
        );
        $destAccess = $this->ciclorutas->nearest(
            (float) $destination['lat'],
            (float) $destination['lng']
        );

        if ($originAccess === null || $destAccess === null) {
            return null;
        }

        $originPt = ['lat' => (float) $originAccess['nearest_lat'], 'lng' => (float) $originAccess['nearest_lng']];
        $destPt = ['lat' => (float) $destAccess['nearest_lat'], 'lng' => (float) $destAccess['nearest_lng']];

        // Si la red conecta ambos accesos directamente, este escenario lo cubre
        // viaCiclorutas() (ruta íntegra por un único componente).
        $directNetwork = $this->ciclorutas->pathBetween($originPt['lat'], $originPt['lng'], $destPt['lat'], $destPt['lng']);
        if ($directNetwork !== null) {
            return null;
        }

        $pref = (float) config('services.map.bike_route_cycle_pref', 0.5);
        $networkWeight = max(0.0, min(1.0, 1.0 - $pref));

        // Salida del componente del origen: el nodo de la red que mejor combina
        // recorrido seguro con cercanía al acceso del componente destino.
        $exit = $this->ciclorutas->exitToward(
            $originPt['lat'],
            $originPt['lng'],
            $destPt['lat'],
            $destPt['lng'],
            $networkWeight
        );

        if ($exit === null || (float) $exit['network_distance_m'] < 150) {
            return null;
        }

        $ride1 = $this->ciclorutas->pathBetween(
            $originPt['lat'],
            $originPt['lng'],
            (float) $exit['lat'],
            (float) $exit['lng']
        );

        if ($ride1 === null) {
            return null;
        }

        $gap = $this->osrmLeg(
            ['lat' => (float) $exit['lat'], 'lng' => (float) $exit['lng']],
            $destPt
        );

        if ($gap === null) {
            return null;
        }

        $maxGapM = (float) config('services.map.bike_route_max_gap_m', 3000);
        if ($gap['distance_m'] > $maxGapM) {
            return null;
        }

        // Tramos de la red en el componente destino, hacia el destino final.
        $ride2 = null;
        $destLegStart = $destPt;
        $destExit = $this->ciclorutas->exitToward(
            $destPt['lat'],
            $destPt['lng'],
            (float) $destination['lat'],
            (float) $destination['lng'],
            $networkWeight
        );

        if ($destExit !== null && (float) $destExit['network_distance_m'] >= 150) {
            $ride2 = $this->ciclorutas->pathBetween(
                $destPt['lat'],
                $destPt['lng'],
                (float) $destExit['lat'],
                (float) $destExit['lng']
            );
            if ($ride2 !== null) {
                $destLegStart = ['lat' => (float) $destExit['lat'], 'lng' => (float) $destExit['lng']];
            }
        }

        $originLeg = $this->osrmLeg($origin, $originPt);
        $destLeg = $this->osrmLeg($destLegStart, $destination);

        if ($originLeg === null || $destLeg === null) {
            return null;
        }

        $networkDistance = (float) $ride1['distance_m'] + (float) ($ride2['distance_m'] ?? 0);
        $totalDistance = $originLeg['distance_m'] + $networkDistance + $gap['distance_m'] + $destLeg['distance_m'];

        // Geometría unida y continua (sin saltos: cada tramo comparte el punto final).
        $coordinates = array_merge(
            $originLeg['coordinates'],
            $ride1['coordinates'],
            $gap['coordinates'],
            $ride2 !== null ? $ride2['coordinates'] : [],
            $destLeg['coordinates']
        );

        $coverage = $totalDistance > 0 ? $networkDistance / $totalDistance : 0.0;

        $maxDetour = (float) config('services.map.bike_route_max_detour', 1.5);
        $minCoverage = (float) config('services.map.bike_route_min_cicloruta_coverage', 0.25);

        if ($directDistanceM !== null && $directDistanceM > 0 && $totalDistance > $directDistanceM * $maxDetour) {
            return null;
        }

        if ($coverage < $minCoverage) {
            return null;
        }

        $speedKmh = (float) config('services.map.bike_avg_speed_kmh', 15);
        $networkDuration = $speedKmh > 0 ? $networkDistance / ($speedKmh * 1000 / 3600) : 0.0;

        $totalDuration = $originLeg['duration_seconds'] + $networkDuration + $gap['duration_seconds'] + $destLeg['duration_seconds'];

        $used = array_merge($ride1['used'] ?? [], $ride2['used'] ?? []);
        $usedNames = [];
        foreach ($used as $u) {
            if ($u['name'] !== '' && ! in_array($u['name'], $usedNames, true)) {
                $usedNames[] = $u['name'];
            }
        }

        $infraStats = $this->ciclorutas->routeInfraStats($coordinates, (float) $totalDistance);

        return [
            'id' => 'via-ciclorutas-hybrid',
            'distance_m' => (float) round($totalDistance, 1),
            'duration_seconds' => (float) round($totalDuration, 1),
            'distance_km' => round($totalDistance / 1000, 2),
            'duration_min' => (int) round($totalDuration / 60),
            'coordinates' => $coordinates,
            'steps' => $this->buildChainedSteps($originLeg, $ride1, $gap, $ride2, $destLeg),
            'summary' => $usedNames !== [] ? implode(' · ', array_slice($usedNames, 0, 4)) : 'Ruta por ciclorrutas',
            'profile' => 'cicle',
            'driver' => 'ciclorutas',
            'label' => '🚲 Ruta por ciclorrutas',
            'via_ciclorutas' => true,
            'cicloruta_badge' => true,
            'ciclorutas_used' => array_slice($usedNames, 0, 8),
            'cicloruta_coverage_pct' => $infraStats['coverage_pct'],
            'cicloruta_safety_index' => $infraStats['safety_index'],
            'cicloruta_matched_m' => $infraStats['matched_m'],
            'cicloruta_by_type_m' => $infraStats['by_type_m'],
            'bike_network_distance_m' => (float) round($networkDistance, 1),
            'bike_network_used' => array_slice($used, 0, 8),
        ];
    }

    /**
     * Añade las métricas de coincidencia con la red ciclista a una ruta.
     *
     * @param  array<string, mixed>  $route
     */
    protected function attachCiclorutaMetrics(array &$route): void
    {
        $stats = $this->ciclorutas->routeInfraStats($route['coordinates'] ?? [], (float) ($route['distance_m'] ?? 0));
        $route['cicloruta_coverage_pct'] = $stats['coverage_pct'];
        $route['cicloruta_safety_index'] = $stats['safety_index'];
        $route['cicloruta_matched_m'] = $stats['matched_m'];
        $route['cicloruta_by_type_m'] = $stats['by_type_m'];
    }

    /**
     * Ordena las rutas por coste ponderado por seguridad ciclista.
     *
     * El coste abarata el tiempo en función del índice de seguridad de la
     * infraestructura. Así, una ruta un poco más larga pero con mucho más
     * tramo por ciclorruta (y de tipo más protegido) puede quedar por delante
     * sin romper el equilibrio con distancia/tiempo.
     *
     * @param  array<int, array<string, mixed>>  $routes
     */
    protected function sortByCiclorutaPriority(array &$routes): void
    {
        $pref = (float) config('services.map.bike_route_cycle_pref', 0.5);

        usort($routes, function (array $a, array $b) use ($pref): int {
            $costA = $this->ciclorutaPriorityCost($a, $pref);
            $costB = $this->ciclorutaPriorityCost($b, $pref);

            return $costA <=> $costB;
        });
    }

    /**
     * Coste ponderado de una ruta teniendo en cuenta la prioridad ciclista.
     *
     * @param  array<string, mixed>  $route
     */
    protected function ciclorutaPriorityCost(array $route, float $pref): float
    {
        $safety = (float) ($route['cicloruta_safety_index'] ?? 0);
        $safety = max(0.0, min(1.0, $safety));

        $factor = 1.0 - $pref * $safety;
        $duration = (float) ($route['duration_seconds'] ?? 0);
        $distance = (float) ($route['distance_m'] ?? 0);

        // El término de distancia es un desempate leve: a igualdad de coste
        // ponderado gana la ruta más corta.
        return $duration * $factor + $distance * 0.0005;
    }

    /**
     * Indica si entre las rutas hay alguna que recorra la red de ciclorrutas.
     *
     * @param  array<int, array<string, mixed>>  $routes
     */
    public function hasViaCiclorutas(array $routes): bool
    {
        foreach ($routes as $route) {
            if (($route['via_ciclorutas'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtiene una ruta (un solo leg) de OSRM con su geometría, distancia y
     * duración. Devuelve null si el proveedor no responde.
     *
     * @param  array<string, float>  $origin
     * @param  array<string, float>  $destination
     * @return array<string, mixed>|null
     */
    protected function osrmLeg(array $origin, array $destination): ?array
    {
        $routes = $this->driver->routes($origin, $destination, 1);

        return $routes[0] ?? null;
    }

    /**
     * Construye las instrucciones de la ruta priorizada.
     *
     * @param  array<string, mixed>  $originLeg
     * @param  array<int, array<string, mixed>>  $used
     * @param  array<string, mixed>  $destLeg
     * @return array<int, array<string, mixed>>
     */
    protected function buildSteps(array $originLeg, array $used, array $destLeg): array
    {
        $steps = [];

        if ($originLeg['distance_m'] > 25) {
            $steps[] = [
                'instruction' => 'Avanza hasta la ciclorruta más cercana',
                'distance_m' => (float) $originLeg['distance_m'],
                'duration_seconds' => (float) $originLeg['duration_seconds'],
                'bike_infra' => false,
            ];
        }

        $steps = array_merge($steps, $this->networkRideSteps($used));

        if ($destLeg['distance_m'] > 25) {
            $steps[] = [
                'instruction' => 'Sigue hasta tu destino',
                'distance_m' => (float) $destLeg['distance_m'],
                'duration_seconds' => (float) $destLeg['duration_seconds'],
                'bike_infra' => false,
            ];
        }

        $steps[] = [
            'instruction' => 'Has llegado a tu destino',
            'distance_m' => 0,
            'duration_seconds' => 0,
            'bike_infra' => false,
        ];

        return $steps;
    }

    /**
     * Construye las instrucciones de la ruta priorizada con red encadenada
     * (componentes distintos conectados por un tramo OSRM acotado).
     *
     * @param  array<string, mixed>  $originLeg
     * @param  array<string, mixed>  $ride1
     * @param  array<string, mixed>  $gap
     * @param  array<string, mixed>|null  $ride2
     * @param  array<string, mixed>  $destLeg
     * @return array<int, array<string, mixed>>
     */
    protected function buildChainedSteps(array $originLeg, array $ride1, array $gap, ?array $ride2, array $destLeg): array
    {
        $steps = [];

        if ($originLeg['distance_m'] > 25) {
            $steps[] = [
                'instruction' => 'Avanza hasta la ciclorruta más cercana',
                'distance_m' => (float) $originLeg['distance_m'],
                'duration_seconds' => (float) $originLeg['duration_seconds'],
                'bike_infra' => false,
            ];
        }

        $steps = array_merge($steps, $this->networkRideSteps($ride1['used'] ?? []));

        if ($gap['distance_m'] > 25) {
            $steps[] = [
                'instruction' => 'Enlaza con el siguiente tramo de ciclorruta',
                'distance_m' => (float) $gap['distance_m'],
                'duration_seconds' => (float) $gap['duration_seconds'],
                'bike_infra' => false,
            ];
        }

        $steps = array_merge($steps, $this->networkRideSteps($ride2['used'] ?? []));

        if ($destLeg['distance_m'] > 25) {
            $steps[] = [
                'instruction' => 'Sigue hasta tu destino',
                'distance_m' => (float) $destLeg['distance_m'],
                'duration_seconds' => (float) $destLeg['duration_seconds'],
                'bike_infra' => false,
            ];
        }

        $steps[] = [
            'instruction' => 'Has llegado a tu destino',
            'distance_m' => 0,
            'duration_seconds' => 0,
            'bike_infra' => false,
        ];

        return $steps;
    }

    /**
     * Pasos "Continúa por …" para cada tramo de red ciclista utilizado.
     *
     * @param  array<int, array<string, mixed>>  $used
     * @return array<int, array<string, mixed>>
     */
    protected function networkRideSteps(array $used): array
    {
        $steps = [];

        foreach ($used as $u) {
            if ((float) $u['distance_m'] < 25) {
                continue;
            }
            $where = $u['name'] !== '' ? "por {$u['name']}" : "por {$this->ciclorutas->typeLabel($u['type'])}";
            $steps[] = [
                'instruction' => 'Continúa '.$where,
                'distance_m' => (float) $u['distance_m'],
                'duration_seconds' => (float) ($u['distance_m'] / ((float) config('services.map.bike_avg_speed_kmh', 15) * 1000 / 3600)),
                'bike_infra' => true,
            ];
        }

        return $steps;
    }

    /**
     * Añade la etiqueta de la alternativa (recomendada / rápida / tranquila)
     * y una puntuación orientativa de compatibilidad con ciclorrutas.
     *
     * @param  array<string, mixed>  $route
     * @return array<string, mixed>
     */
    protected function decorate(array $route, int $index): array
    {
        if (($route['via_ciclorutas'] ?? false) === true && ! isset($route['label'])) {
            $route['label'] = '🚲 Ruta por ciclorrutas';
        } else {
            $route['label'] = $this->labelFor($route, $index);
        }

        return $route;
    }

    protected function labelFor(array $route, int $index): string
    {
        return match ($index) {
            0 => 'Ruta recomendada',
            1 => 'Ruta alternativa',
            2 => 'Ruta tranquila',
            default => 'Alternativa '.($index + 1),
        };
    }

    // ------------------------------------------------------------------
    // Planificador inteligente por perfiles (FASE 1)
    // ------------------------------------------------------------------

    /**
     * Calcula una ruta para un perfil concreto.
     *
     * Construye el pool común de candidatas reales y deja que la capa de
     * criterios (RouteProfileRanker) elija la mejor para el perfil.
     *
     * @param  array<string, float>  $origin
     * @param  array<string, float>  $destination
     * @return array<string, mixed>|null
     */
    public function routesForProfile(
        array $origin,
        array $destination,
        string|RouteProfile $profile,
        ?int $alternatives = null,
        bool $priorizeCiclorutas = false,
    ): ?array {
        $profile = RouteProfile::tryFromMixed($profile);
        if ($profile === null) {
            return null;
        }

        $pool = $this->candidatePool($origin, $destination, $alternatives, $priorizeCiclorutas);
        $route = $this->ranker->preferred($profile, $pool);

        if ($route === null) {
            return null;
        }

        $route['profile'] = $profile->value;
        $route['label'] = $profile->emoji().' '.$profile->label();
        $route['metadata'] = $this->buildMetadata($route, $profile->value);

        return $route;
    }

    /**
     * Calcula todos los perfiles solicitados sobre el mismo pool de
     * candidatas reales (una sola llamada al motor de rutas).
     *
     * Cada entrada devuelta lleva el perfil, su presentación y la ruta
     * elegida (o null si el perfil no pudo resolverse con datos reales).
     *
     * @param  array<string, float>  $origin
     * @param  array<string, float>  $destination
     * @param  array<int, string>|array<int, RouteProfile>  $profiles
     * @return array<int, array<string, mixed>>
     */
    public function profiles(
        array $origin,
        array $destination,
        array $profiles = [],
        ?int $alternatives = null,
        bool $priorizeCiclorutas = false,
    ): array {
        // Normaliza y filtra la lista solicitada contra los perfiles válidos.
        $requested = $profiles === []
            ? RouteProfile::cases()
            : array_values(array_filter($profiles, fn ($p) => RouteProfile::tryFromMixed($p) !== null));

        $pool = $this->candidatePool($origin, $destination, $alternatives, $priorizeCiclorutas);

        $results = [];
        foreach ($requested as $requestedProfile) {
            $profile = RouteProfile::tryFromMixed($requestedProfile);
            if ($profile === null) {
                continue;
            }

            $entry = [
                'profile' => $profile->value,
                'label' => $profile->label(),
                'emoji' => $profile->emoji(),
                'description' => $profile->description(),
                'route' => null,
            ];

            $route = $this->ranker->preferred($profile, $pool);
            if ($route !== null) {
                $route['profile'] = $profile->value;
                $route['label'] = $profile->emoji().' '.$profile->label();
                $route['metadata'] = $this->buildMetadata($route, $profile->value);
                $entry['route'] = $route;
            }

            $results[] = $entry;
        }

        return $results;
    }

    /**
     * Pool común de candidatas reales para el planificador de perfiles.
     *
     * Candidatas:
     *  1. Rutas del motor activo (OSRM/GraphHopper), que ya respeta las
     *     restricciones de bicicleta;
     *  2. si priorizeCiclorutas, la ruta por la red local de ciclorrutas
     *     (viaCiclorutas o su variante encadenada) cuando es viable.
     *
     * Cada candidata se enriquece con métricas reales de infraestructura
     * ciclista y con los campos normalizados (elevación nullable, etc.).
     * Las candidatas sin geometría válida se descartan.
     *
     * @param  array<string, float>  $origin
     * @param  array<string, float>  $destination
     * @return array<int, array<string, mixed>>
     */
    protected function candidatePool(
        array $origin,
        array $destination,
        ?int $alternatives,
        bool $priorizeCiclorutas,
    ): array {
        $routes = $this->driver->routes($origin, $destination, $alternatives) ?? [];

        $routes = array_values(array_filter(
            $routes,
            fn (array $route) => $this->hasGeometry($route)
        ));

        foreach ($routes as &$route) {
            $this->attachCiclorutaMetrics($route);
            $this->normalizeForProfile($route);
            $this->attachBaseMetadata($route);
        }
        unset($route);

        if ($priorizeCiclorutas) {
            $directDistance = ! empty($routes)
                ? (float) ($routes[0]['distance_m'] ?? 0)
                : null;

            $candidate = $this->viaCiclorutas($origin, $destination, $directDistance);
            if ($candidate === null) {
                $candidate = $this->chainedViaCiclorutas($origin, $destination, $directDistance);
            }

            if ($candidate !== null) {
                $this->normalizeForProfile($candidate);
                $this->attachBaseMetadata($candidate);
                $routes[] = $candidate;
            }
        }

        return $routes;
    }

    /**
     * ¿La ruta tiene geometría mínima válida para dibujarse?
     *
     * @param  array<string, mixed>  $route
     */
    protected function hasGeometry(array $route): bool
    {
        $coordinates = $route['coordinates'] ?? [];

        return is_array($coordinates) && count($coordinates) >= 2;
    }

    /**
     * Normaliza los campos de elevación de una ruta.
     *
     * Solo se conservan valores reales del proveedor. Si no existen, los
     * campos se dejan a null (nunca se simulan) y se marca elevation_available
     * en false. slope_pct se deriva de datos reales (ascent real / distancia
     * real) y únicamente cuando hay elevación real.
     *
     * @param  array<string, mixed>  $route
     */
    protected function normalizeForProfile(array &$route): void
    {
        $elevationAvailable = ($route['elevation_available'] ?? false) === true;
        $route['elevation_available'] = $elevationAvailable;
        $route['elevations'] = $route['elevations'] ?? null;

        $route['ascent_m'] = isset($route['ascent_m']) && is_numeric($route['ascent_m'])
            ? round((float) $route['ascent_m'], 1)
            : null;

        $route['descent_m'] = isset($route['descent_m']) && is_numeric($route['descent_m'])
            ? round((float) $route['descent_m'], 1)
            : null;

        $distance = (float) ($route['distance_m'] ?? 0);
        $route['slope_pct'] = ($elevationAvailable && $route['ascent_m'] !== null && $distance > 0)
            ? round($route['ascent_m'] / $distance * 100, 2)
            : null;
    }

    /**
     * Metadatos base honestos de una ruta (sin perfil).
     *
     * @param  array<string, mixed>  $route
     * @return array<string, mixed>
     */
    protected function attachBaseMetadata(array &$route): void
    {
        $route['metadata'] = $this->baseMetadata($route);
    }

    /**
     * Metadatos de la ruta ya resuelta por un perfil.
     *
     * @param  array<string, mixed>  $route
     * @return array<string, mixed>
     */
    protected function buildMetadata(array $route, ?string $profile): array
    {
        $metadata = $this->baseMetadata($route);
        $notes = [];

        if ($profile === RouteProfile::Easiest->value && ! $metadata['elevation_available']) {
            $notes[] = 'El motor de rutas no entrega datos reales de elevación: el desnivel no se muestra y "Menor esfuerzo" usa la ruta ciclista del motor, que evita según OpenStreetMap las vías más empinadas. La arquitectura está preparada para elevación.';
        }

        if ($profile === RouteProfile::Safest->value) {
            $notes[] = 'Safety Score real 0-100 calculado con datos verificables (red local de ciclorrutas, OpenStreetMap y siniestralidad oficial cuando está habilitada). Si la información disponible es insuficiente, no se publica una puntuación: se indica "Información de seguridad insuficiente" con su confianza.';
        }

        if ($profile === RouteProfile::Scenic->value && $metadata['cicloruta_coverage_pct'] === 0) {
            $notes[] = 'Aún no se disponen de datos locales de tranquilidad (parques, jerarquía de vía); se prioriza la infraestructura ciclista real cuando existe.';
        }

        $metadata['notes'] = $notes;

        return $metadata;
    }

    /**
     * Metadatos comunes de cualquier ruta (estructura estable de fase 1).
     *
     * @param  array<string, mixed>  $route
     * @return array<string, mixed>
     */
    protected function baseMetadata(array $route): array
    {
        // Evaluación de seguridad completa (fase 2): incluye el score real
        // 0-100, su confianza, la explicación y las señales que lo sostienen.
        // Nunca publica un número si los datos reales no lo respaldan.
        $assessment = $this->safety->assessment($route);

        return [
            'elevation_available' => ($route['elevation_available'] ?? false) === true,
            'ascent_m' => $route['ascent_m'] ?? null,
            'descent_m' => $route['descent_m'] ?? null,
            'slope_pct' => $route['slope_pct'] ?? null,
            'cicloruta_coverage_pct' => (int) ($route['cicloruta_coverage_pct'] ?? 0),
            'safety_score' => $assessment['score'] ?? null,
            'safety_confidence' => $assessment['confidence'] ?? 0.0,
            'safety_confidence_label' => $assessment['confidence_label'] ?? 'Baja',
            'safety_explanation' => $assessment['explanation'] ?? null,
            'safety_signals' => $assessment['signals'] ?? [],
            'safety_sources' => $assessment['sources'] ?? $this->safety->sources(),
            'safety' => $assessment,
            'notes' => [],
        ];
    }

    /**
     * Servicio de seguridad activo (fase 2: Safety Score real 0-100).
     */
    public function safetyScoring(): SafetyScoringService
    {
        return $this->safety;
    }
}
