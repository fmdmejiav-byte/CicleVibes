<?php

namespace App\Services\Maps;

use Illuminate\Container\Container;
use SplPriorityQueue;

/**
 * Servicio de la red de ciclorrutas de Barranquilla.
 *
 * Carga la red desde resources/data/ciclorutas-barranquilla.geojson y
 * ofrece dos operaciones utilizadas por el planificador:
 *
 *  - nearest():        el segmento de ciclorruta más cercano a un punto
 *                      (proyección real sobre la geometría de la línea).
 *  - pathBetween():    un recorrido a lo largo de la red de ciclorrutas
 *                      entre dos accesos, calculado sobre un grafo
 *                      (Dijkstra) construido a partir de los segmentos.
 *
 * Los tipos de infraestructura siguen la tipología del mapa oficial de
 * ciclorrutas de Barranquilla:
 *  - ciclorruta_calzada
 *  - ciclorruta_anden
 *  - ciclobanda
 *  - carril_ciclo_preferente
 */
class CiclorutaService
{
    /**
     * Etiquetas y estilos por tipo de infraestructura.
     *
     * @var array<string, array{label: string, color: string, emoji: string}>
     */
    public const TYPES = [
        'ciclorruta_calzada' => ['label' => 'Ciclorruta en calzada', 'color' => '#dc2626', 'emoji' => '🔴'],
        'ciclorruta_anden' => ['label' => 'Ciclorruta en andén', 'color' => '#ea580c', 'emoji' => '🟠'],
        'ciclobanda' => ['label' => 'Ciclobanda', 'color' => '#ca8a04', 'emoji' => '🟡'],
        'carril_ciclo_preferente' => ['label' => 'Carril ciclo preferente', 'color' => '#2563eb', 'emoji' => '🔵'],
    ];

    /**
     * Peso de seguridad por tipo de infraestructura (0..1).
     *
     * Cuanto más protegida es la infraestructura, mayor su valor: una
     * ciclorruta segregada de la calzada es más segura que una ciclobanda
     * pintada, y esta que un carril compartido ("carril ciclo preferente").
     */
    public const SAFETY_WEIGHTS = [
        'ciclorruta_calzada' => 1.0,
        'ciclorruta_anden' => 0.9,
        'ciclobanda' => 0.7,
        'carril_ciclo_preferente' => 0.5,
    ];

    /**
     * Ruta al archivo GeoJSON de la red.
     */
    protected string $file;

    /**
     * Red completa (FeatureCollection) cacheada en memoria.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $network = null;

    /**
     * Catálogo de segmentos normalizado a [[lat, lng], ...].
     *
     * @var array<int, array<string, mixed>>|null
     */
    protected ?array $catalog = null;

    /**
     * Índice espacial de segmentos (celdas => índices de features).
     *
     * @var array<string, array<int, int>>|null
     */
    protected ?array $segmentIndex = null;

    /**
     * Grafo de la red: nodos y aristas con su atribución a cada segmento.
     *
     * @var array{nodes: array<string,int>, adj: array<int,array<int,array<string,mixed>>>}|null
     */
    protected ?array $graph = null;

    /**
     * Tolerancia (metros) para unir extremos de segmentos próximos.
     */
    protected float $joinToleranceM = 20.0;

    /**
     * Umbral (metros) a partir del cual una proximidad entre segmentos se
     * considera sospechosa: se registra para revisión, nunca se conecta.
     */
    protected float $suspiciousToleranceM = 50.0;

    /**
     * Componentes conectados del grafo (tras unir nodos por tolerancia).
     *
     * @var array<int, array<string, mixed>>|null
     */
    protected ?array $components = null;

    /**
     * Diagnóstico del grafo: uniones automáticas y conexiones sospechosas.
     *
     * @var array<string, mixed>
     */
    protected array $diagnostics = [
        'auto_joined_pairs' => 0,
        'suspicious_pairs' => [],
        'suspicious_count' => 0,
    ];

    public function __construct(?string $file = null)
    {
        $this->file = $file ?? resource_path('data/ciclorutas-barranquilla.geojson');

        if (Container::getInstance()->bound('config')) {
            $this->joinToleranceM = (float) config('services.map.ciclorutas_node_join_tolerance_m', 20.0);
            $this->suspiciousToleranceM = (float) config('services.map.ciclorutas_join_suspicious_tolerance_m', 50.0);
        }
        $this->suspiciousToleranceM = max($this->suspiciousToleranceM, $this->joinToleranceM);
    }

    /**
     * Devuelve la red completa como FeatureCollection GeoJSON.
     *
     * @return array<string, mixed>
     */
    public function network(): array
    {
        if ($this->network === null) {
            $this->network = $this->loadFile();
        }

        return $this->network;
    }

    /**
     * Lista de los tipos de infraestructura con etiqueta y color.
     *
     * @return array<string, array{label: string, color: string, emoji: string}>
     */
    public function types(): array
    {
        return self::TYPES;
    }

    /**
     * Etiqueta legible de un tipo de infraestructura.
     */
    public function typeLabel(string $type): string
    {
        return self::TYPES[$type]['label'] ?? 'Infraestructura ciclista';
    }

    /**
     * Peso de seguridad (0..1) de un tipo de infraestructura.
     *
     * Tipos que no son infraestructura ciclista devuelven 0 (para no
     * favorecer tramos de calzada genérica).
     */
    public function safetyWeight(string $type): float
    {
        return self::SAFETY_WEIGHTS[$type] ?? 0.0;
    }

    /**
     * Métricas de coincidencia de una geometría de ruta con la red ciclista.
     *
     * Muestrea cada tramo de la ruta y comprueba si pasa cerca de algún
     * segmento de la red (tolerancia lateral configurable). Devuelve cuántos
     * metros transcurren sobre (o muy junto a) infraestructura ciclista,
     * desglosados por tipo, junto con una cobertura (%) y un índice de
     * seguridad ponderado por el tipo de infraestructura.
     *
     * @param  array<int, array{0: float, 1: float}>  $coords  [[lat, lng], ...]
     * @param  float|null  $totalMOverride  Longitud oficial de la ruta (metros)
     *                                      por si la geometría decodificada no la
     *                                      refleja (p. ej. legs OSRM artificiales
     *                                      en tests); se usa como denominador de
     *                                      cobertura/seguridad.
     * @return array<string, mixed>
     */
    public function routeInfraStats(array $coords, ?float $totalMOverride = null): array
    {
        $matchedM = 0.0;
        $byTypeM = [];
        $count = count($coords);

        if ($count < 2) {
            return $this->emptyInfraStats();
        }

        $tolerance = $this->matchToleranceM();
        $sampleStep = 25.0;
        $sampledM = 0.0;

        for ($i = 0; $i < $count - 1; $i++) {
            $a = $coords[$i];
            $b = $coords[$i + 1];
            $segLen = $this->haversine((float) $a[0], (float) $a[1], (float) $b[0], (float) $b[1]);
            $sampledM += $segLen;

            $samples = max(1, (int) ceil($segLen / $sampleStep));
            for ($k = 0; $k < $samples; $k++) {
                $t = ($k + 0.5) / $samples;
                $lat = (float) $a[0] + ((float) $b[0] - (float) $a[0]) * $t;
                $lng = (float) $a[1] + ((float) $b[1] - (float) $a[1]) * $t;

                $near = $this->nearestSegment($lat, $lng, $tolerance);
                if ($near === null || $near['distance_m'] > $tolerance) {
                    continue;
                }

                $stepLen = $segLen / $samples;
                $matchedM += $stepLen;

                $type = $near['type'] !== '' ? $near['type'] : 'desconocida';
                $byTypeM[$type] = ($byTypeM[$type] ?? 0.0) + $stepLen;
            }
        }

        $totalM = $totalMOverride !== null && $totalMOverride > $matchedM
            ? $totalMOverride
            : max($sampledM, $matchedM);

        $safetyM = 0.0;
        foreach ($byTypeM as $type => $meters) {
            $safetyM += $meters * $this->safetyWeight($type);
        }

        return [
            'total_m' => round($totalM, 1),
            'matched_m' => round($matchedM, 1),
            'unmatched_m' => round($totalM - $matchedM, 1),
            'by_type_m' => array_map(fn ($m) => round($m, 1), $byTypeM),
            'coverage_pct' => $totalM > 0 ? (int) round(min(1.0, $matchedM / $totalM) * 100) : 0,
            'safety_index' => $totalM > 0 ? round(min(1.0, $safetyM / $totalM), 3) : 0.0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyInfraStats(): array
    {
        return [
            'total_m' => 0.0,
            'matched_m' => 0.0,
            'unmatched_m' => 0.0,
            'by_type_m' => [],
            'coverage_pct' => 0,
            'safety_index' => 0.0,
        ];
    }

    /**
     * Segmento de la red más cercano a un punto (limitado espacialmente a
     * los candidatos del índice), con su distancia y tipo.
     *
     * @return array{distance_m: float, type: string}|null
     */
    protected function nearestSegment(float $lat, float $lng, float $toleranceM): ?array
    {
        $index = $this->segmentIndex();
        $cellDeg = $this->segmentCellDeg();
        $ring = (int) ceil($toleranceM / ($cellDeg * 111000.0));
        $ring = max(1, $ring);

        $latCell = (int) floor($lat / $cellDeg);
        $lngCell = (int) floor($lng / $cellDeg);
        $candidates = [];

        for ($dx = -$ring; $dx <= $ring; $dx++) {
            for ($dy = -$ring; $dy <= $ring; $dy++) {
                $bucket = ($latCell + $dx).':'.($lngCell + $dy);
                foreach ($index[$bucket] ?? [] as $featureIndex) {
                    $candidates[$featureIndex] = true;
                }
            }
        }

        $best = null;
        foreach (array_keys($candidates) as $featureIndex) {
            $feature = $this->features()[$featureIndex] ?? null;
            if ($feature === null) {
                continue;
            }
            [$dist] = $this->distanceToLine($lat, $lng, $feature['coords']);
            if ($best === null || $dist < $best['distance_m']) {
                $best = [
                    'distance_m' => $dist,
                    'type' => (string) ($feature['properties']['type'] ?? ''),
                ];
            }
        }

        return $best;
    }

    /**
     * Índice espacial de la red (una sola vez): para cada celda, los
     * índices de los segmentos cuya caja envolvente la toca.
     *
     * @return array<string, array<int, int>>
     */
    protected function segmentIndex(): array
    {
        if ($this->segmentIndex !== null) {
            return $this->segmentIndex;
        }

        $cellDeg = $this->segmentCellDeg();
        $index = [];

        foreach ($this->features() as $featureIndex => $feature) {
            $coords = $feature['coords'];
            if (count($coords) < 2) {
                continue;
            }

            $minLat = $maxLat = $coords[0][0];
            $minLng = $maxLng = $coords[0][1];
            foreach ($coords as $pt) {
                $minLat = min($minLat, $pt[0]);
                $maxLat = max($maxLat, $pt[0]);
                $minLng = min($minLng, $pt[1]);
                $maxLng = max($maxLng, $pt[1]);
            }

            $c1 = [(int) floor($minLat / $cellDeg), (int) floor($minLng / $cellDeg)];
            $c2 = [(int) floor($maxLat / $cellDeg), (int) floor($maxLng / $cellDeg)];

            for ($lat = $c1[0]; $lat <= $c2[0]; $lat++) {
                for ($lng = $c1[1]; $lng <= $c2[1]; $lng++) {
                    $index[$lat.':'.$lng][] = $featureIndex;
                }
            }
        }

        $this->segmentIndex = $index;

        return $this->segmentIndex;
    }

    /**
     * Nodo de la red (índice de grafo) más cercano a unas coordenadas.
     */
    public function nearestNodeAt(float $lat, float $lng): ?int
    {
        return $this->nearestNode($lat, $lng);
    }

    /**
     * Nodo de salida de un componente hacia un destino.
     *
     * Resuelve el punto de la red (en el mismo componente que origen) que
     * minimiza el tiempo percibido combinado: recorrido por la red desde el
     * acceso de origen (ponderado y, por tanto, más atractivo cuanto mayor
     * es el peso de seguridad) más el hueco en línea recta hasta el destino.
     * Sin esa ponderación, el óptimo siempre sería "no andar en bici por la
     * red" (cada metro ganado en el hueco cuesta un metro de red), y la red
     * nunca influiría en la selección.
     *
     * @param  float  $networkWeight  Factor 0..1 que abarata los metros recorridos
     *                                por la red (1 - prioridad ciclista).
     * @return array<string, mixed>|null
     */
    public function exitToward(float $fromLat, float $fromLng, float $toLat, float $toLng, float $networkWeight = 0.6): ?array
    {
        $graph = $this->graph();
        $from = $this->nearestNode($fromLat, $fromLng);
        if ($from === null) {
            return null;
        }

        $distances = $this->dijkstraDistances($from);
        $nodeIdByIndex = array_flip($graph['nodes']);
        $networkWeight = max(0.0, min(1.0, $networkWeight));

        $best = null;
        $bestScore = INF;

        foreach ($distances as $nodeIndex => $networkM) {
            if (! is_finite($networkM)) {
                continue;
            }
            $parts = explode(',', $nodeIdByIndex[$nodeIndex], 2);
            $gapM = $this->haversine(
                (float) $parts[0], (float) $parts[1],
                $toLat, $toLng
            );

            $score = $networkWeight * $networkM + $gapM;
            if ($score < $bestScore) {
                $bestScore = $score;
                $best = [
                    'node' => $nodeIndex,
                    'lat' => (float) $parts[0],
                    'lng' => (float) $parts[1],
                    'network_distance_m' => $networkM,
                    'gap_m' => $gapM,
                ];
            }
        }

        return $best;
    }

    /**
     * Distancias (metros) por Dijkstra desde un nodo del grafo (tabla completa).
     *
     * @return array<int, float> Índice de nodo => distancia total.
     */
    public function dijkstraDistances(int $from): array
    {
        $graph = $this->graph();
        $count = count($graph['nodes']);
        $dist = array_fill(0, $count, INF);
        $dist[$from] = 0.0;

        $queue = new SplPriorityQueue;
        $queue->insert([$from, 0.0], 0.0);

        while (! $queue->isEmpty()) {
            [$node, $d] = $queue->extract();
            if ($d > $dist[$node] + 1e-9) {
                continue;
            }
            foreach ($graph['adj'][$node] ?? [] as $edge) {
                $candidate = $dist[$node] + $edge['dist'];
                if ($candidate < $dist[$edge['to']] - 1e-9) {
                    $dist[$edge['to']] = $candidate;
                    $queue->insert([$edge['to'], $candidate], -$candidate);
                }
            }
        }

        return $dist;
    }

    /**
     * Tolerancia (metros) para considerar que una ruta "coincide" con la red.
     */
    protected function matchToleranceM(): float
    {
        return $this->configFloat('ciclorutas_match_tolerance_m', 30.0);
    }

    /**
     * Tamaño de celda (grados) del índice espacial de segmentos (~222 m).
     */
    protected function segmentCellDeg(): float
    {
        return 0.002;
    }

    /**
     * Lee una clave de configuración con respaldo por defecto sin depender
     * de que el contenedor de la aplicación esté inicializado (p. ej. en
     * scripts de mantenimiento).
     */
    protected function configFloat(string $key, float $default): float
    {
        if (Container::getInstance()->bound('config')) {
            return (float) config("services.map.{$key}", $default);
        }

        return $default;
    }

    /**
     * Encuentra el segmento de ciclorruta más cercano a unas coordenadas.
     *
     * La distancia se calcula proyectando el punto sobre la geometría de
     * cada línea (punto al segmento), no usando el centro del segmento.
     *
     * @return array<string, mixed>|null
     */
    public function nearest(float $lat, float $lng): ?array
    {
        $best = null;
        $features = $this->features();

        foreach ($features as $feature) {
            $coords = $feature['coords'];
            [$dist, $projLat, $projLng] = $this->distanceToLine($lat, $lng, $coords);

            if ($best === null || $dist < $best['distance_m']) {
                $type = (string) ($feature['properties']['type'] ?? '');
                $best = [
                    'name' => (string) ($feature['properties']['name'] ?? ''),
                    'type' => $type,
                    'type_label' => $this->typeLabel($type),
                    'distance_m' => round($dist, 1),
                    'nearest_lat' => round($projLat, 6),
                    'nearest_lng' => round($projLng, 6),
                    'segment' => [
                        'type' => 'Feature',
                        'properties' => $feature['properties'],
                        'geometry' => [
                            'type' => 'LineString',
                            'coordinates' => $feature['geometry']['coordinates'],
                        ],
                    ],
                ];
            }
        }

        return $best;
    }

    /**
     * Busca un recorrido dentro de la red de ciclorrutas entre dos accesos.
     *
     * Los puntos de acceso se aproximan al vértice más cercano de la red y
     * se resuelve el camino mínimo (Dijkstra) con las aristas de los
     * segmentos. Devuelve la geometría, la distancia y la atribución de
     * cada ciclorruta utilizada.
     *
     * @return array<string, mixed>|null
     */
    public function pathBetween(float $fromLat, float $fromLng, float $toLat, float $toLng): ?array
    {
        $graph = $this->graph();

        $fromId = $this->nearestNode($fromLat, $fromLng);
        $toId = $this->nearestNode($toLat, $toLng);

        if ($fromId === null || $toId === null || $fromId === $toId) {
            return null;
        }

        $result = $this->dijkstra($graph, $fromId, $toId);
        if ($result === null) {
            return null;
        }

        [$nodePath, $total] = $result;
        $coordinates = [];
        $used = [];
        $nodeIds = array_flip($graph['nodes']);

        foreach ($nodePath as $i => $nodeIndex) {
            $coordinates[] = explode(',', $nodeIds[$nodeIndex], 2);
            $coordinates[$i][0] = (float) $coordinates[$i][0];
            $coordinates[$i][1] = (float) $coordinates[$i][1];
        }

        $usedDists = [];
        foreach ($nodePath as $i => $nodeIndex) {
            if ($i === 0) {
                continue;
            }
            $prev = $nodePath[$i - 1];
            $edge = $this->findEdge($graph['adj'][$prev], $nodeIndex);
            if ($edge === null || $edge['feat']['key'] === 'join') {
                continue;
            }
            $feat = $edge['feat'];
            if (! isset($usedDists[$feat['key']])) {
                $usedDists[$feat['key']] = [
                    'name' => $feat['name'],
                    'type' => $feat['type'],
                    'type_label' => $feat['type_label'],
                    'distance_m' => 0.0,
                ];
            }
            $usedDists[$feat['key']]['distance_m'] += $edge['dist'];
        }

        $used = array_values($usedDists);
        usort($used, fn ($a, $b) => $b['distance_m'] <=> $a['distance_m']);

        foreach ($used as &$u) {
            $u['distance_m'] = round($u['distance_m'], 1);
        }
        unset($u);

        return [
            'coordinates' => $coordinates,
            'distance_m' => round($total, 1),
            'used' => $used,
        ];
    }

    /**
     * Carga y normaliza el archivo GeoJSON.
     *
     * @return array<string, mixed>
     */
    protected function loadFile(): array
    {
        if (! is_file($this->file)) {
            return ['type' => 'FeatureCollection', '_meta' => [], 'features' => []];
        }

        $decoded = json_decode((string) file_get_contents($this->file), true);
        if (! is_array($decoded) || ! isset($decoded['features'])) {
            return ['type' => 'FeatureCollection', '_meta' => [], 'features' => []];
        }

        return $decoded;
    }

    /**
     * Segmentos internos normalizados ([lat, lng] en 'coords' y originales
     * [lng, lat] en 'geometry').
     *
     * @return array<int, array<string, mixed>>
     */
    protected function features(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }

        $this->catalog = [];
        foreach ($this->network()['features'] ?? [] as $feature) {
            $lngLat = $feature['geometry']['coordinates'] ?? [];
            if (! is_array($lngLat) || count($lngLat) < 2) {
                continue;
            }
            $latLng = [];
            foreach ($lngLat as $point) {
                $latLng[] = [(float) $point[1], (float) $point[0]];
            }
            $this->catalog[] = [
                'properties' => $feature['properties'],
                'geometry' => $feature['geometry'],
                'coords' => $latLng,
            ];
        }

        return $this->catalog;
    }

    /**
     * Distancia (metros) de un punto a una línea, con el punto proyectado.
     *
     * Usa proyección equirectangular local (misma técnica que el navegador)
     * para no depender de la curvatura en el área urbana.
     *
     * @param  array<int, array{0: float, 1: float}>  $line  Lat/lng de la línea.
     * @return array{0: float, 1: float, 2: float} [distancia, lat, lng]
     */
    protected function distanceToLine(float $lat, float $lng, array $line): array
    {
        $best = [INF, $lat, $lng];

        for ($i = 0; $i < count($line) - 1; $i++) {
            [$ax, $ay] = $line[$i];       // lat, lng
            [$bx, $by] = $line[$i + 1];

            $abLat = $bx - $ax;
            $abLng = $by - $ay;
            $apLat = $lat - $ax;
            $apLng = $lng - $ay;

            $ab2 = $abLat * $abLat + $abLng * $abLng;
            $t = $ab2 > 0 ? ($apLat * $abLat + $apLng * $abLng) / $ab2 : 0;
            $t = min(1, max(0, $t));

            $cLat = $ax + $t * $abLat;
            $cLng = $ay + $t * $abLng;

            $dLat = ($lat - $cLat) * 111320;
            $dLng = ($lng - $cLng) * 111320 * cos($cLat * M_PI / 180);
            $dist = sqrt($dLat * $dLat + $dLng * $dLng);

            if ($dist < $best[0]) {
                $best = [$dist, $cLat, $cLng];
            }
        }

        return $best;
    }

    /**
     * Distancia Haversine en metros entre dos puntos.
     */
    protected function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371000.0;
        $p1 = $lat1 * M_PI / 180;
        $p2 = $lat2 * M_PI / 180;
        $dp = ($lat2 - $lat1) * M_PI / 180;
        $dl = ($lng2 - $lng1) * M_PI / 180;

        $h = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;

        return 2 * $R * asin(sqrt($h));
    }

    /**
     * Construye (una sola vez) el grafo de la red.
     *
     * @return array{nodes: array<string,int>, adj: array<int,array<int,array<string,mixed>>>}
     */
    protected function graph(): array
    {
        if ($this->graph !== null) {
            return $this->graph;
        }

        $nodes = [];
        $adj = [];

        foreach ($this->features() as $index => $feature) {
            $properties = $feature['properties'];
            $name = (string) ($properties['name'] ?? '');
            $type = (string) ($properties['type'] ?? '');
            $feat = [
                'key' => (string) ($index),
                'name' => $name,
                'type' => $type,
                'type_label' => $this->typeLabel($type),
            ];

            $coords = $feature['coords'];
            $prevNodeIndex = null;
            $prevCoordIndex = null;

            foreach ($coords as $coordIndex => $point) {
                [$lat, $lng] = $point;
                $id = $this->nodeId($lat, $lng);
                if (! isset($nodes[$id])) {
                    $nodes[$id] = count($nodes);
                    $adj[$nodes[$id]] = [];
                }
                $cur = $nodes[$id];

                if ($prevNodeIndex !== null) {
                    $dist = $this->haversine(
                        $coords[$prevCoordIndex][0], $coords[$prevCoordIndex][1],
                        $lat, $lng
                    );
                    $adj[$prevNodeIndex][] = ['to' => $cur, 'dist' => $dist, 'feat' => $feat];
                    $adj[$cur][] = ['to' => $prevNodeIndex, 'dist' => $dist, 'feat' => $feat];
                }

                $prevNodeIndex = $cur;
                $prevCoordIndex = $coordIndex;
            }
        }

        $this->joinNearbyNodes($adj, $nodes);

        $this->graph = ['nodes' => $nodes, 'adj' => $adj];

        return $this->graph;
    }

    /**
     * Une por tolerancia los nodos de la red que están muy próximos.
     *
     * En OpenStreetMap las vías que se cruzan no siempre comparten el mismo
     * nodo (mismo par de coordenadas). Esto añade aristas "coste cero" entre
     * nodos que distan menos de la tolerancia configurada, para que la red no
     * quede fragmentada en componentes inconexos.
     *
     * Tolerancias diferenciadas:
     *  - <= joinToleranceM (20 m):      conexión automática segura.
     *  - 20-50 m (suspicious):          se registra para revisión, NO se conecta.
     *  - > 50 m:                        no se conecta automáticamente.
     *
     * @param  array<int, array<int, array<string, mixed>>>  $adj
     * @param  array<string, int>  $nodes
     */
    protected function joinNearbyNodes(array &$adj, array $nodes): void
    {
        $this->diagnostics = ['auto_joined_pairs' => 0, 'suspicious_pairs' => [], 'suspicious_count' => 0];

        if ($this->joinToleranceM <= 0 || count($nodes) < 2) {
            return;
        }

        $step = $this->joinToleranceM / 111000.0;
        $ring = (int) ceil($this->suspiciousToleranceM / (111000.0 * $step));
        $meta = [];
        $buckets = [];

        foreach ($nodes as $id => $index) {
            [$lat, $lng] = explode(',', $id);
            $meta[$index] = [(float) $lat, (float) $lng];
            $buckets[(int) floor($lat / $step).':'.(int) floor($lng / $step)][] = $index;
        }

        $feat = ['key' => 'join', 'name' => '', 'type' => '', 'type_label' => ''];
        $joined = [];
        $suspicious = [];

        foreach ($buckets as $key => $indices) {
            [$bx, $by] = array_map('intval', explode(':', $key, 2));

            foreach ($indices as $a) {
                [$latA, $lngA] = $meta[$a];

                for ($dx = -$ring; $dx <= $ring; $dx++) {
                    for ($dy = -$ring; $dy <= $ring; $dy++) {
                        $neighbors = $buckets[($bx + $dx).':'.($by + $dy)] ?? null;
                        if ($neighbors === null || count($neighbors) > 2000) {
                            continue;
                        }

                        foreach ($neighbors as $b) {
                            if ($b === $a) {
                                continue;
                            }

                            $pair = $a < $b ? $a.':'.$b : $b.':'.$a;

                            [$latB, $lngB] = $meta[$b];
                            $dist = $this->haversine($latA, $lngA, $latB, $lngB);

                            if ($dist <= $this->joinToleranceM) {
                                if (isset($joined[$pair]) || $this->findEdge($adj[$a], $b) !== null) {
                                    continue;
                                }
                                $joined[$pair] = true;
                                $this->diagnostics['auto_joined_pairs']++;
                                $adj[$a][] = ['to' => $b, 'dist' => 0.0, 'feat' => $feat];
                                $adj[$b][] = ['to' => $a, 'dist' => 0.0, 'feat' => $feat];
                            } elseif ($dist <= $this->suspiciousToleranceM && ! isset($suspicious[$pair])) {
                                $suspicious[$pair] = [
                                    'distance_m' => round($dist, 1),
                                    'a' => ['lat' => round($latA, 6), 'lng' => round($lngA, 6)],
                                    'b' => ['lat' => round($latB, 6), 'lng' => round($lngB, 6)],
                                    'a_features' => $this->nodeFeatureKeys($adj, $a),
                                    'b_features' => $this->nodeFeatureKeys($adj, $b),
                                ];
                            }
                        }
                    }
                }
            }
        }

        $this->diagnostics['suspicious_pairs'] = array_values($suspicious);
        foreach ($suspicious as $pair) {
            $this->diagnostics['suspicious_count']++;
        }
    }

    /**
     * Claves de los segmentos (features) que tocan un nodo.
     *
     * @param  array<int, array<int, array<string, mixed>>>  $adj
     * @return array<int, string>
     */
    protected function nodeFeatureKeys(array $adj, int $node): array
    {
        $keys = [];
        foreach ($adj[$node] ?? [] as $edge) {
            if ($edge['feat']['key'] !== 'join' && ! in_array((string) $edge['feat']['key'], $keys, true)) {
                $keys[] = (string) $edge['feat']['key'];
            }
        }

        return $keys;
    }

    /**
     * Distancia (metros) de un segmento (suma Haversine entre vértices).
     */
    protected function featureLength(array $feature): float
    {
        $coords = $feature['coords'];
        $total = 0.0;

        for ($i = 0; $i < count($coords) - 1; $i++) {
            $total += $this->haversine($coords[$i][0], $coords[$i][1], $coords[$i + 1][0], $coords[$i + 1][1]);
        }

        return $total;
    }

    /**
     * Kilómetros totales y por tipo de infraestructura.
     *
     * @return array{total_km: float, by_type_km: array<string, float>, by_type_count: array<string, int>}
     */
    public function kilometersByType(): array
    {
        $meters = [];
        $counts = [];
        $total = 0.0;

        foreach ($this->features() as $feature) {
            $type = (string) ($feature['properties']['type'] ?? '');
            $len = $this->featureLength($feature);
            $meters[$type] = ($meters[$type] ?? 0.0) + $len;
            $counts[$type] = ($counts[$type] ?? 0) + 1;
            $total += $len;
        }

        return [
            'total_km' => round($total / 1000.0, 2),
            'by_type_km' => array_map(fn ($m) => round($m / 1000.0, 2), $meters),
            'by_type_count' => $counts,
        ];
    }

    /**
     * Componentes conexos del grafo.
     *
     * @param  bool  $withJoins  true = red del planificador (tras unir nodos por
     *                           tolerancia); false = topología bruta (solo nodos
     *                           exactamente compartidos).
     * @return array{components: array<int, array<string, mixed>>, by_node: array<int, int>}
     */
    public function connectedComponents(bool $withJoins = true): array
    {
        $graph = $this->graph();
        $adj = $graph['adj'];
        $count = count($adj);

        $nodeComp = array_fill(0, $count, -1);
        $components = [];
        $id = 0;

        for ($i = 0; $i < $count; $i++) {
            if ($nodeComp[$i] !== -1) {
                continue;
            }

            $stack = [$i];
            $nodeComp[$i] = $id;
            $members = [];

            while ($stack) {
                $n = array_pop($stack);
                $members[] = $n;

                foreach ($adj[$n] ?? [] as $edge) {
                    if (! $withJoins && $edge['feat']['key'] === 'join') {
                        continue;
                    }
                    if ($nodeComp[$edge['to']] === -1) {
                        $nodeComp[$edge['to']] = $id;
                        $stack[] = $edge['to'];
                    }
                }
            }

            $components[] = ['id' => $id, 'nodes_count' => count($members), 'nodes' => $members];
            $id++;
        }

        usort($components, fn ($a, $b) => $b['nodes_count'] <=> $a['nodes_count']);

        return ['components' => $components, 'by_node' => $nodeComp];
    }

    /**
     * Diagnóstico del grafo de la red (uniones automáticas y conexiones
     * sospechosas registradas, sin conectar).
     *
     * @return array<string, mixed>
     */
    public function diagnostics(): array
    {
        $this->graph(); // asegura que el diagnóstico esté calculado

        return $this->diagnostics;
    }

    /**
     * Conectividad sospechosa (20-50 m por defecto) registrada para revisión.
     *
     * @return array<int, array<string, mixed>>
     */
    public function suspiciousGaps(): array
    {
        $this->graph(); // asegura que el diagnóstico esté calculado

        return $this->diagnostics['suspicious_pairs'] ?? [];
    }

    /**
     * Componentes con atribución: km, nombres, tipos y punto representativo.
     *
     * @param  array{components: array<int, array<string, mixed>>, by_node: array<int, int>}  $components
     * @return array<int, array<string, mixed>>
     */
    protected function componentDetails(array $components): array
    {
        $byFeatureComp = [];

        foreach ($this->features() as $index => $feature) {
            $first = $feature['coords'][0];
            $idx = $components['by_node'][$this->nodeIndexOf((float) $first[0], (float) $first[1])] ?? -1;
            if ($idx === -1) {
                continue;
            }
            $byFeatureComp[$index] = $idx;
        }

        $details = [];
        foreach ($components['components'] as $c) {
            $details[$c['id']] = [
                'id' => $c['id'],
                'nodes_count' => $c['nodes_count'],
                'km' => 0.0,
                'features_count' => 0,
                'names' => [],
                'types' => [],
                'representative' => null,
            ];
        }

        foreach ($byFeatureComp as $index => $cid) {
            $length = $this->featureLength($this->features()[$index]);
            $name = (string) ($this->features()[$index]['properties']['name'] ?? '');
            $type = (string) ($this->features()[$index]['properties']['type'] ?? '');

            $details[$cid]['km'] += $length;
            $details[$cid]['features_count']++;
            if ($name !== '') {
                $details[$cid]['names'][$name] = ($details[$cid]['names'][$name] ?? 0.0) + $length;
            }
            $details[$cid]['types'][$type] = ($details[$cid]['types'][$type] ?? 0.0) + $length;
        }

        foreach ($details as &$d) {
            $coords = $this->componentCenter($components, $d['id']);
            $d['representative'] = $coords;
            $d['km'] = round($d['km'] / 1000.0, 3);
            arsort($d['names']);
            $d['names'] = array_map(fn ($m) => round($m / 1000.0, 3), $d['names']);
            arsort($d['types']);
            $d['types'] = array_map(fn ($m) => round($m / 1000.0, 3), $d['types']);
        }
        unset($d);

        return array_values($details);
    }

    /**
     * Índice de nodo para unas coordenadas, o null si no pertenecen al grafo.
     */
    protected function nodeIndexOf(float $lat, float $lng): ?int
    {
        $graph = $this->graph();

        return $graph['nodes'][$this->nodeId($lat, $lng)] ?? null;
    }

    /**
     * Punto central aproximado de un componente (promedio de nodos).
     *
     * @param  array{components: array<int, array<string, mixed>>, by_node: array<int, int>}  $components
     * @return array{lat: float, lng: float}|null
     */
    protected function componentCenter(array $components, int $id): ?array
    {
        $graph = $this->graph();
        $coords = [];

        foreach ($components['components'] as $c) {
            if ($c['id'] !== $id) {
                continue;
            }
            foreach ($c['nodes'] as $node) {
                $nodeIds = array_flip($graph['nodes']);
                $parts = explode(',', $nodeIds[$node], 2);
                $coords[] = [(float) $parts[0], (float) $parts[1]];
            }
        }

        if ($coords === []) {
            return null;
        }

        $lat = array_sum(array_column($coords, 0)) / count($coords);
        $lng = array_sum(array_column($coords, 1)) / count($coords);

        return ['lat' => round($lat, 6), 'lng' => round($lng, 6)];
    }

    /**
     * Distancia entre componentes: para cada componente, el hueco mínimo hasta
     * el resto de la red. Solo se busca hasta $limitM (más allá se reporta null
     * porque no hay conexión razonable sin inventar geometría).
     *
     * @param  array{components: array<int, array<string, mixed>>, by_node: array<int, int>}  $components
     * @return array<int, array<string, mixed>>
     */
    protected function componentGaps(array $components, float $limitM = 500.0): array
    {
        $graph = $this->graph();
        $nodeCoords = [];
        foreach ($graph['nodes'] as $id => $idx) {
            [$lat, $lng] = explode(',', $id);
            $nodeCoords[$idx] = [(float) $lat, (float) $lng];
        }

        $result = [];
        foreach ($components['components'] as $c) {
            $result[$c['id']] = [
                'id' => $c['id'],
                'nearest_component_id' => null,
                'nearest_gap_m' => null,
            ];
        }

        $step = $this->joinToleranceM / 111000.0;
        $ring = (int) ceil($limitM / (111000.0 * $step));
        $grid = [];
        foreach ($components['by_node'] as $node => $cid) {
            $lat = $nodeCoords[$node][0];
            $lng = $nodeCoords[$node][1];
            $grid[(int) floor($lat / $step).':'.(int) floor($lng / $step)][] = $node;
        }

        foreach ($components['components'] as $c) {
            $best = null;

            foreach ($c['nodes'] as $a) {
                [$aLat, $aLng] = $nodeCoords[$a];

                // Búsqueda en malla limitada al alcance mínimo buscado.
                $cells = [];
                $keyA = (int) floor($aLat / $step).':'.(int) floor($aLng / $step);
                [$bx, $by] = array_map('intval', explode(':', $keyA, 2));

                for ($dx = -$ring; $dx <= $ring; $dx++) {
                    for ($dy = -$ring; $dy <= $ring; $dy++) {
                        $cells[] = ($bx + $dx).':'.($by + $dy);
                    }
                }

                foreach ($cells as $cellKey) {
                    foreach ($grid[$cellKey] ?? [] as $b) {
                        if ($components['by_node'][$b] === $c['id']) {
                            continue;
                        }
                        $dist = $this->haversine($aLat, $aLng, $nodeCoords[$b][0], $nodeCoords[$b][1]);
                        if ($dist > $limitM) {
                            continue;
                        }
                        if ($best === null || $dist < $best[0]) {
                            $best = [$dist, $components['by_node'][$b]];
                        }
                    }
                }
            }

            if ($best !== null) {
                $result[$c['id']]['nearest_gap_m'] = round($best[0], 1);
                $result[$c['id']]['nearest_component_id'] = $best[1];
            }
        }

        return array_values($result);
    }

    /**
     * Auditoría completa de la red de ciclorrutas.
     *
     * Genera el reporte de calidad y cobertura usado por el comando
     * `php artisan ciclorutas:audit`.
     *
     * @return array<string, mixed>
     */
    public function auditReport(): array
    {
        $this->graph();

        $features = $this->features();
        $featuresCount = count($features);
        $kms = $this->kilometersByType();

        // Duplicados y segmentos cortos.
        $seen = [];
        $duplicates = [];
        $shortSegments = [];
        $lengths = [];

        foreach ($features as $i => $feature) {
            $len = round($this->featureLength($feature), 1);
            $lengths[$i] = $len;
            $key = json_encode($feature['geometry']['coordinates']);

            if (isset($seen[$key])) {
                $duplicates[] = [
                    'index' => $i,
                    'duplicate_of' => $seen[$key],
                    'name' => (string) ($feature['properties']['name'] ?? ''),
                ];
            } else {
                $seen[$key] = $i;
            }

            if ($len < 20.0) {
                $shortSegments[] = [
                    'index' => $i,
                    'name' => (string) ($feature['properties']['name'] ?? ''),
                    'type' => (string) ($feature['properties']['type'] ?? ''),
                    'length_m' => $len,
                ];
            }
        }

        // Segmentos aislados: ambos extremos con grado 1 y sin enlaces de unión.
        $isolated = $this->isolatedSegments();

        // Componentes antes y después de unir por tolerancia.
        $before = $this->connectedComponents(false);
        $after = $this->connectedComponents(true);
        $detailsBefore = $this->componentDetails($before);
        $detailsAfter = $this->componentDetails($after);
        $gapsBefore = $this->componentGaps($before);

        // Corredores (agregación por nombre + tipo).
        $corridors = $this->corridors();

        // Conexiones sospechosas enriquecidas con nombre/tipo de segmento.
        $suspicious = [];
        foreach ($this->diagnostics['suspicious_pairs'] ?? [] as $s) {
            $suspicious[] = [
                'distance_m' => $s['distance_m'],
                'a' => $s['a'],
                'b' => $s['b'],
                'a_features' => array_map(fn ($k) => $this->featureLabel((int) $k), $s['a_features']),
                'b_features' => array_map(fn ($k) => $this->featureLabel((int) $k), $s['b_features']),
            ];
        }

        return [
            'generated_at' => now()->toDateTimeString(),
            'file' => $this->file,
            'summary' => [
                'features_count' => $featuresCount,
                'total_km' => $kms['total_km'],
                'by_type_km' => $kms['by_type_km'],
                'by_type_count' => $kms['by_type_count'],
                'official_reference_total_km' => (float) ($this->network()['_meta']['official_total_km'] ?? null),
                'components_before_join' => count($before['components']),
                'components_after_join' => count($after['components']),
                'auto_connected_gaps_lt_20m' => $this->diagnostics['auto_joined_pairs'] ?? 0,
                'suspicious_gaps_20_50m' => $this->diagnostics['suspicious_count'] ?? 0,
                'duplicate_segments' => count($duplicates),
                'short_segments_lt_20m' => count($shortSegments),
                'isolated_segments' => count($isolated),
            ],
            'duplicates' => $duplicates,
            'short_segments' => $shortSegments,
            'isolated_segments' => $isolated,
            'components' => [
                'before_join' => $this->externalizeComponents($detailsBefore, $gapsBefore),
                'after_join' => $detailsAfter,
            ],
            'corridors' => $corridors,
            'suspicious_gaps' => $suspicious,
        ];
    }

    /**
     * Etiqueta legible de un segmento (índice) para reportes.
     */
    protected function featureLabel(int $key): string
    {
        $feature = $this->features()[$key] ?? null;
        if ($feature === null) {
            return "#{$key}";
        }
        $name = (string) ($feature['properties']['name'] ?? '') ?: '(sin nombre)';
        $type = $this->typeLabel((string) ($feature['properties']['type'] ?? ''));

        return "#{$key} · {$name} · {$type}";
    }

    /**
     * Segmentos aislados: ninguno de sus nodos comparte otro segmento ni queda
     * a menos de la tolerancia segura de otro segmento.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function isolatedSegments(): array
    {
        $graph = $this->graph();
        $adj = $graph['adj'];
        $isolated = [];

        foreach ($this->features() as $index => $feature) {
            $degree1 = $this->nodeDegreeReal($adj, $this->nodeIndexOf($feature['coords'][0][0], $feature['coords'][0][1]));
            $degreeN = $this->nodeDegreeReal($adj, $this->nodeIndexOf($feature['coords'][count($feature['coords']) - 1][0], $feature['coords'][count($feature['coords']) - 1][1]));
            $joinedA = $this->nodeHasJoin($adj, $this->nodeIndexOf($feature['coords'][0][0], $feature['coords'][0][1]));
            $joinedN = $this->nodeHasJoin($adj, $this->nodeIndexOf($feature['coords'][count($feature['coords']) - 1][0], $feature['coords'][count($feature['coords']) - 1][1]));

            // Aislado si ambos extremos son de grado 1 y ninguno fue unido.
            if (($degree1 ?? 99) === 1 && ($degreeN ?? 99) === 1 && ! $joinedA && ! $joinedN) {
                $isolated[] = [
                    'index' => $index,
                    'name' => (string) ($feature['properties']['name'] ?? ''),
                    'type' => (string) ($feature['properties']['type'] ?? ''),
                    'length_m' => round($this->featureLength($feature), 1),
                ];
            }
        }

        return $isolated;
    }

    /**
     * Grado de un nodo contando solo aristas reales (sin joins).
     *
     * @param  array<int, array<int, array<string, mixed>>>  $adj
     */
    protected function nodeDegreeReal(array $adj, ?int $node): int
    {
        if ($node === null) {
            return 99;
        }
        $degree = 0;
        foreach ($adj[$node] ?? [] as $edge) {
            if ($edge['feat']['key'] !== 'join') {
                $degree++;
            }
        }

        return $degree;
    }

    /**
     * Indica si un nodo participa en alguna conexión de unión (tolerancia).
     *
     * @param  array<int, array<int, array<string, mixed>>>  $adj
     */
    protected function nodeHasJoin(array $adj, ?int $node): bool
    {
        if ($node === null) {
            return false;
        }
        foreach ($adj[$node] ?? [] as $edge) {
            if ($edge['feat']['key'] === 'join') {
                return true;
            }
        }

        return false;
    }

    /**
     * Corredores: agregación por (nombre, tipo) con km, nº de segmentos y
     * componentes que atraviesa.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function corridors(): array
    {
        $graph = $this->graph();
        $components = $this->connectedComponents(true);
        $rows = [];

        foreach ($this->features() as $index => $feature) {
            $name = (string) ($feature['properties']['name'] ?? '');
            $type = (string) ($feature['properties']['type'] ?? '');
            $key = $name."\n".$type;
            $first = $feature['coords'][0];
            $cid = $components['by_node'][$this->nodeIndexOf((float) $first[0], (float) $first[1])] ?? -1;

            if (! isset($rows[$key])) {
                $rows[$key] = [
                    'name' => $name ?: '(sin nombre)',
                    'type' => $type,
                    'type_label' => $this->typeLabel($type),
                    'km' => 0.0,
                    'segments_count' => 0,
                    'components' => [],
                ];
            }
            $rows[$key]['km'] += $this->featureLength($feature);
            $rows[$key]['segments_count']++;
            if ($cid !== -1) {
                $rows[$key]['components'][$cid] = true;
            }
        }

        $result = [];
        foreach ($rows as $row) {
            $row['km'] = round($row['km'] / 1000.0, 3);
            $row['components'] = array_keys($row['components']);
            $result[] = $row;
        }

        usort($result, fn ($a, $b) => $b['km'] <=> $a['km']);

        return $result;
    }

    /**
     * Normaliza los componentes para el reporte (sin la lista plana de nodos).
     *
     * @param  array<int, array<string, mixed>>  $details
     * @param  array<int, array<string, mixed>>  $gaps
     * @return array<int, array<string, mixed>>
     */
    protected function externalizeComponents(array $details, array $gaps): array
    {
        $byId = [];
        foreach ($gaps as $g) {
            $byId[$g['id']] = $g;
        }

        foreach ($details as &$d) {
            $d['nearest_gap_m'] = $byId[$d['id']]['nearest_gap_m'] ?? null;
            $d['nearest_component_id'] = $byId[$d['id']]['nearest_component_id'] ?? null;
        }
        unset($d);

        return $details;
    }

    protected function nodeId(float $lat, float $lng): string
    {
        return sprintf('%.6f,%.6f', $lat, $lng);
    }

    /**
     * Devuelve el nodo más cercano a unas coordenadas.
     */
    protected function nearestNode(float $lat, float $lng): ?int
    {
        $graph = $this->graph();
        $best = null;
        $bestDist = INF;

        foreach ($graph['nodes'] as $id => $index) {
            [$lat2, $lng2] = explode(',', $id);
            $dist = $this->haversine($lat, $lng, (float) $lat2, (float) $lng2);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $index;
            }
        }

        return $best;
    }

    /**
     * Recupera la arista (phantom) entre dos nodos para la atribución.
     *
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<string, mixed>|null
     */
    protected function findEdge(array $edges, int $to): ?array
    {
        foreach ($edges as $edge) {
            if ($edge['to'] === $to) {
                return $edge;
            }
        }

        return null;
    }

    /**
     * Camino mínimo (por distancia) en el grafo de la red.
     *
     * @param  array{nodes: array<string,int>, adj: array<int,array<int,array<string,mixed>>>}  $graph
     * @return array{0: array<int,int>, 1: float}|null [índices de nodos, distancia total]
     */
    protected function dijkstra(array $graph, int $from, int $to): ?array
    {
        $count = count($graph['nodes']);
        $dist = array_fill(0, $count, INF);
        $prev = array_fill(0, $count, null);
        $unvisited = new SplPriorityQueue;

        $dist[$from] = 0.0;
        $unvisited->insert([$from, 0.0], 0.0);

        while (! $unvisited->isEmpty()) {
            [$node, $d] = $unvisited->extract();

            if ($d > $dist[$node] + 1e-9) {
                continue;
            }
            if ($node === $to) {
                break;
            }

            foreach ($graph['adj'][$node] ?? [] as $edge) {
                $candidate = $dist[$node] + $edge['dist'];
                if ($candidate < $dist[$edge['to']] - 1e-9) {
                    $dist[$edge['to']] = $candidate;
                    $prev[$edge['to']] = $node;
                    $unvisited->insert([$edge['to'], $candidate], -$candidate);
                }
            }
        }

        if ($prev[$to] === null) {
            return null;
        }

        $path = [];
        $current = $to;
        while ($current !== null) {
            $path[] = $current;
            $current = $prev[$current] ?? null;
        }
        $path = array_reverse($path);

        return [$path, $dist[$to]];
    }
}
