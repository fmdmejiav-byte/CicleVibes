<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Safety Score de CicleVibes
    |--------------------------------------------------------------------------
    | Configuración del Safety Score real (0-100) para rutas ciclistas.
    |
    | REGLA DE NEGOCIO: el score se calcula SOLO con datos reales y
    | verificables (OpenStreetMap vía Overpass, red local de ciclorrutas y,
    | si está habilitada, la fuente oficial de siniestralidad que se
    | configure). Nunca se inventan puntuaciones, accidentes, tráfico,
    | iluminación o infraestructura.
    |
    | Cuando no hay datos suficientes el score es null y la UI muestra
    | "Información de seguridad insuficiente".
    */

    'enabled' => (bool) env('SAFETY_ENABLED', true),

    // Magnitud de la puntuación (0-100).
    'unit' => '0-100',

    // Mínimo de factores con datos reales para producir un score.
    'min_factors' => (int) env('SAFETY_MIN_FACTORS', 2),

    // Confianza mínima (0..1) bajo la cual el sistema NO publica un score
    // (prefiere null antes que un número poco respaldado).
    'min_confidence' => (float) env('SAFETY_MIN_CONFIDENCE', 0.25),

    // Umbrales de etiqueta de confianza ("Alta/Media/Baja").
    'confidence' => [
        'high' => 0.70,
        'medium' => 0.45,
        'low' => 0.0,
    ],

    // Etiquetas de la confianza en español.
    'labels' => [
        'high' => 'Alta',
        'medium' => 'Media',
        'low' => 'Baja',
    ],

    /*
    |--------------------------------------------------------------------------
    | Factores y pesos (modificables sin tocar la lógica del servicio)
    |--------------------------------------------------------------------------
    | Cada factor tiene un peso (0..1). El score final es la media ponderada
    | de los factores con datos disponibles, dividida entre la suma de los
    | pesos de esos factores. Si no se pudo medir un factor, no se penaliza:
    | simplemente no aporta y reduce la confianza (menos información real).
    */

    'factors' => [
        // Infraestructura ciclista real: índice de la red local de
        // CicleVibes (por tipo: ciclorruta en calzada, en andén, ciclobanda,
        // carril preferente) y/o carriles bici documentados en OSM.
        'infrastructure' => ['enabled' => true, 'weight' => 0.28],

        // Jerarquía de vía real desde OSM (residencial, secundaria, primaria,
        // etc.) con su puntuación según exposición al tráfico.
        'road_type' => ['enabled' => true, 'weight' => 0.22],

        // Velocidad máxima permitida cuando OSM la documenta (maxspeed).
        'speed' => ['enabled' => true, 'weight' => 0.18],

        // Iluminación cuando OSM la documenta (lit).
        'lighting' => ['enabled' => true, 'weight' => 0.10],

        // Superficie del pavimento cuando OSM la documenta (surface).
        'surface' => ['enabled' => true, 'weight' => 0.08],

        // Siniestralidad oficial (dataset Socrata configurable) cuando el
        // proveedor está habilitado y detecta sectores críticos cerca de la
        // ruta. Fuera de la cobertura del dataset NO se penaliza ni se premia.
        'accidents' => ['enabled' => true, 'weight' => 0.14],
    ],

    /*
    |--------------------------------------------------------------------------
    | Puntuación por jerarquía de vía (highway en OSM)
    |--------------------------------------------------------------------------
    | Solo se aplica sobre la etiqueta highway REAL presente en el tramo. Si
    | una vía no tiene highway, ese muestreo cuenta como "sin dato".
    */

    'road_type_scores' => [
        'motorway' => 10,
        'trunk' => 10,
        'primary' => 25,
        'secondary' => 40,
        'tertiary' => 55,
        'unclassified' => 65,
        'residential' => 80,
        'living_street' => 90,
        'service' => 80,
        'cycleway' => 90,
        'path' => 90,
        'footway' => 90,
        'pedestrian' => 90,
        'track' => 40,
        'steps' => 50,
        'construction' => 30,
    ],

    // Valor por defecto para etiquetas highway reales no explicitadas arriba.
    'road_type_default_score' => 60,

    /*
    |--------------------------------------------------------------------------
    | Puntuación por velocidad máxima (maxspeed real en OSM, en km/h)
    |--------------------------------------------------------------------------
    | Los tramos sin maxspeed documentado no se puntúan (cuentan como "sin
    | dato" para la cobertura). Nunca se asume una velocidad máxima.
    */

    'maxspeed_scores' => [
        20 => 100,
        30 => 85,
        40 => 70,
        50 => 55,
        60 => 40,
        70 => 25,
        80 => 15,
        120 => 0,
    ],

    // Pendiente máxima (km/h) a partir de la cual la velocidad es 0.
    'maxspeed_score_floor_from' => 80,

    /*
    |--------------------------------------------------------------------------
    | Iluminación (etiqueta lit real en OSM)
    |--------------------------------------------------------------------------
    | lit=yes/automatic/24/7 → 100; lit=no/unlit → 45 (menor seguridad
    | nocturna documentada). Ausencia de etiqueta → "sin dato" (no penaliza).
    */

    'lighting_scores' => [
        'yes' => 100,
        'automatic' => 100,
        '24/7' => 100,
        'no' => 45,
        'unlit' => 45,
    ],

    /*
    |--------------------------------------------------------------------------
    | Superficie (etiqueta surface real en OSM)
    |--------------------------------------------------------------------------
    | Solo se usa cuando la etiqueta existe. Ausencia → "sin dato".
    */

    'surface_scores' => [
        'asphalt' => 100,
        'paved' => 100,
        'concrete' => 100,
        'concrete:plates' => 100,
        'sett' => 100,
        'paving_stones' => 90,
        'cobblestone' => 80,
        'compacted' => 75,
        'fine_gravel' => 70,
        'gravel' => 60,
        'pebblestone' => 55,
        'unpaved' => 45,
        'dirt' => 35,
        'ground' => 30,
        'sand' => 20,
    ],

    // Superficie por defecto para etiquetas surface reales no listadas.
    'surface_default_score' => 60,

    // Peso (0..1) de cada tipo de carril bici documentado en OSM para el
    // factor de infraestructura: segregado > carril dedicado > compartido.
    'cycleway_scores' => [
        'track' => 1.0,
        'separate' => 1.0,
        'opposite_track' => 1.0,
        'lane' => 0.7,
        'opposite_lane' => 0.7,
        'shared_lane' => 0.5,
        'share_busway' => 0.5,
        'shared' => 0.5,
        'opposite' => 0.5,
    ],

    // Peso del carril bici cuando la vía no declara cycleway pero sí
    // bicycle=designated.
    'cycleway_designated_score' => 0.8,

    /*
    |--------------------------------------------------------------------------
    | Proveedor OpenStreetMap (Overpass API)
    |--------------------------------------------------------------------------
    | Consultas acotadas al corredor de la ruta (muestreo de puntos y radio),
    | cacheadas por corredor con TTL configurable, y con timeout corto. Si la
    | API falla, el sistema continúa sin ese proveedor y baja la confianza.
    */

    'osm' => [
        'enabled' => (bool) env('SAFETY_OSM_ENABLED', true),

        'overpass_url' => (string) env('SAFETY_OVERPASS_URL', 'https://overpass-api.de/api/interpreter'),

        // Distancia (metros) alrededor de cada punto muestreado para buscar la
        // vía más cercana y para la consulta de Overpass.
        'corridor_radius_m' => (int) env('SAFETY_OSM_CORRIDOR_RADIUS_M', 60),

        // Paso de muestreo de puntos sobre la geometría de la ruta (metros).
        'sample_step_m' => (int) env('SAFETY_OSM_SAMPLE_STEP_M', 60),

        // Máximo de puntos muestreados por ruta (acota el tamaño de la query).
        'max_samples' => (int) env('SAFETY_OSM_MAX_SAMPLES', 250),

        'timeout' => (int) env('SAFETY_OSM_TIMEOUT', 25),

        // TTL (segundos) del caché por corredor. Pese a mantener la URL
        // pública de datos, reduce drásticamente las consultas repetidas.
        'cache_ttl' => (int) env('SAFETY_OSM_CACHE_TTL', 86400),
    ],

    /*
    |--------------------------------------------------------------------------
    | Proveedor de siniestralidad (datos abiertos oficiales, Socrata)
    |--------------------------------------------------------------------------
    | DESACTIVADO por defecto. Cuando alguien lo habilite debe configurar un
    | dataset público con coordenadas y licencia compatible.
    |
    | Valor por defecto: "SECTORES CRITICOS DE SINIESTRALIDAD VIAL" (ANSV),
    | datos.gov.co/d/rs3u-8r4q — licencia CC BY-SA 4.0, cobertura NACIONAL
    | sobre la red vial de carreteras (primaria y secundaria), sectores
    | críticos con fallecidos (2015-2019), actualización anual.
    |
    | Regla honesta: el factor de siniestralidad SOLO pesa cuando el dataset
    | registra sectores críticos cerca de la ruta (count > 0). Si no hay
    | coincidencias no se concluye "seguro": el factor se excluye para no
    | extrapolar la cobertura (urbana vs. carretera).
    */

    'accidents' => [
        'enabled' => (bool) env('SAFETY_ACCIDENTS_ENABLED', false),

        'provider' => env('SAFETY_ACCIDENTS_PROVIDER', 'socrata'),

        'socrata' => [
            'base_url' => env('SAFETY_ACCIDENTS_SOCRATA_URL', 'https://www.datos.gov.co/resource'),
            // Dataset público de la ANSV (CC BY-SA 4.0), sin API key requerida
            // para volumen bajo; puede añadirse un app token gratuito.
            'dataset_id' => env('SAFETY_ACCIDENTS_DATASET', 'rs3u-8r4q'),
            'app_token' => env('SAFETY_ACCIDENTS_APP_TOKEN', ''),
            'lat_column' => env('SAFETY_ACCIDENTS_LAT_COLUMN', 'latitud'),
            'lng_column' => env('SAFETY_ACCIDENTS_LNG_COLUMN', 'longitud'),

            // Radio (metros) del corredor alrededor de la ruta para buscar
            // sectores críticos registrados.
            'radius_m' => (int) env('SAFETY_ACCIDENTS_RADIUS_M', 200),

            // Cota de resultados por consulta (limit).
            'max_rows' => (int) env('SAFETY_ACCIDENTS_MAX_ROWS', 2000),

            'timeout' => (int) env('SAFETY_ACCIDENTS_TIMEOUT', 20),

            'cache_ttl' => (int) env('SAFETY_ACCIDENTS_CACHE_TTL', 86400),

            // Puntos de score restados por cada siniestro por kilómetro real
            // de ruta encontrado en el corredor.
            'penalty_per_density' => (float) env('SAFETY_ACCIDENTS_PENALTY_PER_DENSITY', 25),

            // Fuente documentada (nombre, URL, licencia, cobertura).
            'source' => [
                'name' => 'Sectores críticos de siniestralidad vial (ANSV)',
                'url' => 'https://www.datos.gov.co/d/rs3u-8r4q',
                'license' => 'CC BY-SA 4.0',
                'coverage' => 'Nacional: red vial de carreteras (primaria y secundaria), sectores críticos con fallecidos 2015-2019, actualización anual.',
            ],
        ],
    ],

];