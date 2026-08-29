<?php

namespace App\Services\Routing;

use App\Services\Maps\CiclorutaService;

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
    public function __construct(
        protected BicycleRoutingDriver $driver,
        protected CiclorutaService $ciclorutas,
    ) {}

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
            $routes[] = $this->decorate($route, $index);
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
}
