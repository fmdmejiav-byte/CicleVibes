<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mapas de CicleVibes (Leaflet + OpenStreetMap)
    |--------------------------------------------------------------------------
    | Configuración de la funcionalidad de mapas basada en tecnologías
    | abiertas. Los proveedores externos (tiles, Nominatim, OSRM) se
    | configuran aquí y pueden sustituirse por proveedores comerciales
    | cambiando estas variables de entorno.
    */
    'map' => [
        'provider' => env('MAP_PROVIDER', 'stadia'),

        // Tiles oscuros (por defecto) para integrar el mapa con el tema dark
        // de la aplicación. Se usan los tiles de Stadia Maps (Alidade Smooth
        // Dark): compatibles con Leaflet, sin API key para desarrollo local
        // y usuarios no comerciales. Sustituible por cualquier proveedor vía
        // .env (MAP_TILES_URL); para producción de pago se puede añadir la
        // API key gratuita de Stadia con ?api_key=... en la URL.
        'tiles_url' => env('MAP_TILES_URL', 'https://tiles.stadiamaps.com/tiles/alidade_smooth_dark/{z}/{x}/{y}.png'),
        'tiles_attribution' => env(
            'MAP_TILES_ATTRIBUTION',
            '&copy; <a href="https://stadiamaps.com/">Stadia Maps</a> &copy; <a href="https://openmaptiles.org/">OpenMapTiles</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        ),
        'tiles_max_zoom' => (int) env('MAP_TILES_MAX_ZOOM', 19),

        // Centro y zoom por defecto (Barranquilla, Colombia)
        'default_lat' => (float) env('MAP_DEFAULT_LAT', 10.9871),
        'default_lng' => (float) env('MAP_DEFAULT_LNG', -74.7890),
        'default_zoom' => (int) env('MAP_DEFAULT_ZOOM', 13),

        // Nominatim (geocodificación): respetar su política de uso.
        'nominatim_url' => env('MAP_NOMINATIM_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('MAP_USER_AGENT', 'CicleVibes/1.0 (mapa de rutas para bicicletas)'),
        'search_limit' => (int) env('MAP_SEARCH_LIMIT', 6),

        // OSRM (cálculo de rutas): perfil orientado a bicicleta cuando exista.
        'osrm_url' => env('MAP_OSRM_URL', 'http://router.project-osrm.org'),
        'osrm_profile' => env('MAP_OSRM_PROFILE', 'cycling'),
        'osrm_alternatives' => (int) env('MAP_OSRM_ALTERNATIVES', 2),

        // Driver de rutas activo: 'osrm' (sin clave, por defecto) o 'graphhopper'.
        // Para GraphHopper conviene configurar MAP_GRAPHHOPPER_KEY en .env.
        'routing_driver' => env('MAP_ROUTING_DRIVER', 'osrm'),

        // GraphHopper (motor de rutas para bicicleta; requiere API key real).
        // Sujeto a su política de uso. Dejar MAP_GRAPHHOPPER_KEY vacía si no se usa.
        'graphhopper_url' => env('MAP_GRAPHHOPPER_URL', 'https://graphhopper.com/api/1'),
        'graphhopper_key' => env('MAP_GRAPHHOPPER_KEY', ''),
        'graphhopper_profile' => env('MAP_GRAPHHOPPER_PROFILE', 'bike'),

        // Overpass API: consulta de infraestructura ciclista (ciclorutas) en OpenStreetMap.
        // Respetar su política de uso: consultas razonables y con límites.
        'overpass_url' => env('MAP_OVERPASS_URL', 'https://overpass-api.de/api/interpreter'),
        'overpass_timeout' => (int) env('MAP_OVERPASS_TIMEOUT', 25),

        // Número máximo de alternativas que se muestran al usuario.
        'alternatives_count' => (int) env('MAP_ALTERNATIVES_COUNT', 3),

        // Ruta "Priorizar ciclorrutas": factor máximo admitido de desviación
        // de la ruta que usa la red de ciclorrutas respecto a la ruta directa.
        // 1.5 = la ruta por ciclorrutas puede ser hasta 50% más larga antes de
        // descartarse y volver a la ruta directa de OSRM.
        'bike_route_max_detour' => (float) env('BIKE_ROUTE_MAX_DETOUR', 1.5),

        // Fracción mínima del trayecto que debe transcurrir sobre infraestructura
        // ciclista (0.25 = 25%) para que la ruta se presente como "Ruta por
        // ciclorrutas". Si la red solo aporta menos que eso, la mayor parte del
        // recorrido sería por calles normales, así que se usa OSRM directo y se
        // presenta como alternativa (evitar etiquetar la ruta como ciclorruta
        // cuando solo una pequeña parte usa infraestructura ciclista).
        'bike_route_min_cicloruta_coverage' => (float) env('BIKE_ROUTE_MIN_CICLORUTA_COVERAGE', 0.25),

        // Fuerza de la preferencia ciclista al ordenar las alternativas con
        // "Priorizar ciclorrutas" activo (0..1). 0.5 abarata a la mitad el
        // tiempo percibido de los tramos sobre infraestructura ciclista, de
        // modo que una ruta algo más larga pero con mucho más ciclorruta
        // puede quedar recomendada sin dejar de tener en cuenta distancia y
        // tiempo.
        'bike_route_cycle_pref' => (float) env('BIKE_ROUTE_CYCLE_PREF', 0.5),

        // Hueco máximo (metros) admitido entre componentes de la red para
        // conectar con un leg OSRM una ruta priorizada "en cadena". Evita
        // saltos largos y desconectados entre tramos de ciclorruta. En la red
        // real de Barranquilla los componentes quedan de media a ~1.5-2.5 km
        // por carretera, así que 3000 m permite la conexión típica sin dejar
        // de acotar los saltos absurdos.
        'bike_route_max_gap_m' => (float) env('BIKE_ROUTE_MAX_GAP_M', 3000),

        // Tolerancia lateral (metros) para considerar que una ruta "coincide"
        // con la red de ciclorrutas al puntuar su cobertura/seguridad.
        'ciclorutas_match_tolerance_m' => (float) env('CICLORUTAS_MATCH_TOLERANCE_M', 30),

        // Velocidad media (km/h) usada para estimar el tiempo de los tramos
        // que recorren la red de ciclorrutas (OSRM no puede estimarlos porque
        // su geometría la calcula nuestra red, no el servidor de rutas).
        'bike_avg_speed_kmh' => (float) env('BIKE_AVG_SPEED_KMH', 15),

        // Velocidad media máxima (km/h) que se considera razonable para una
        // bicicleta en ciudad. Si la duración devuelta por OSRM (perfil
        // 'cycling') implicara una velocidad media mayor, se recalcula a
        // partir de la distancia real de la geometría con bike_avg_speed_kmh.
        // Evita mostrar rutas largas con tiempos irrealmente cortos.
        'bike_route_max_expected_speed_kmh' => (float) env('BIKE_ROUTE_MAX_EXPECTED_SPEED_KMH', 25),

        // Límite de resultados de marcadores de bicicletas por petición.
        'bicicletas_limit' => (int) env('MAP_BICICLETAS_LIMIT', 100),

        // Ruta de la red GeoJSON de ciclorrutas (relativa a resources/data).
        'ciclorutas_geojson' => env('MAP_CICLORUTAS_GEOJSON', 'ciclorutas-barranquilla.geojson'),

        // Tolerancia (metros) para conectar extremos de segmentos de ciclorruta
        // que en OpenStreetMap no comparten exactamente el mismo nodo. Unir
        // nodos próximos evita que la red quede fragmentada en componentes
        // inconexos y permite rutas continuas por infraestructura ciclista.
        'ciclorutas_node_join_tolerance_m' => (float) env('CICLORUTAS_NODE_JOIN_TOLERANCE_M', 20),

        // Umbral a partir del cual una proximidad entre segmentos se considera
        // sospechosa: NO se conecta automáticamente (una conexión artificial de
        // 100-200 m podría crear rutas falsas), solo se registra para revisión
        // en el reporte de auditoría (php artisan ciclorutas:audit).
        'ciclorutas_join_suspicious_tolerance_m' => (float) env('CICLORUTAS_JOIN_SUSPICIOUS_TOLERANCE_M', 50),
    ],

];
