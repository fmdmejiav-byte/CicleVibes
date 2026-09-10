# CicleVibes

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Planificador inteligente de rutas (FASE 1 y FASE 2)

CicleVibes ofrece 5 perfiles de ruta para bicicleta: **rápida**, **corta**, **menor esfuerzo**, **tranquila** y **segura**. Todos se calculan sobre un pool común de candidatas reales (motor de rutas activo + red de ciclorrutas cuando se prioriza), y una capa de criterios (`RouteProfileRanker`) elige la mejor para cada perfil sin duplicar motores. En la fase 2 el perfil **segura** se ordena por un **Safety Score real de 0 a 100** calculado con datos abiertos y verificables.

### Arquitectura

- `app/Enums/RouteProfile.php` — enum con los 5 perfiles (clave, etiqueta, emoji y descripción en español).
- `app/Contracts/SafetyScoringService.php` — contrato del Safety Score: `score()` (0-100) y `assessment()` (score, confianza, explicación, señales y fuentes).
- `app/Services/Routing/Safety/InfrastructureSafetyScoringService.php` — implementación de respaldo (sin red): solo infraestructura ciclista real y `score()` devuelve `null` (nunca inventa puntuaciones).
- `app/Services/Routing/Safety/SafetyScoreService.php` — implementación de producción (fase 2): combina señales reales de OpenStreetMap y un dataset oficial de siniestralidad (cuando está habilitado), con pesos, confianza y explicación.
- `app/Services/Routing/RouteProfileRanker.php` — criterios por perfil: menor duración (fastest), menor distancia (shortest), menor desnivel real si el proveedor lo entrega (easiest), mayor cobertura de infraestructura ciclista (scenic) y mayor Safety Score real precalculado (safest), con desempates documentados.
- `app/Services/Routing/BicycleRoutingService.php` — `routesForProfile()`, `profiles()` y el pool común de candidatas (`candidatePool()`), más metadatos de fase 1 y el Safety Score completo en `metadata.*`.

### Endpoints

- `GET /api/maps/profiles` — calcula los perfiles `profiles` (CSV) sobre el mismo pool; devuelve una entrada por perfil con su ruta (o `null` si no fue viable). `422` si ninguna ruta es viable.
- `GET /api/maps/recalculate` — acepta `profile` opcional para recalcular conservando el perfil durante la navegación.

### Datos y limitaciones (honestas)

- La elevación solo se muestra si el **proveedor** la entrega. El OSRM público no incluye elevación: los campos quedan a `null` con `elevation_available=false`. Con GraphHopper (`routing_driver=graphhopper` + clave) y un servidor con elevación, `bike` puede devolver `ascend`/`descend` reales que alimentan "menor esfuerzo".
- El Safety Score completo se calcula en la fase 2 con datos reales y verificables (ver sección siguiente); cuando los datos disponibles no alcanzan, la UI muestra **"Información de seguridad insuficiente"** en vez de inventar un valor.

### Safety Score real (FASE 2)

Score de **0 a 100** por ruta, calculado con fuentes abiertas y documentadas. Nunca inventa datos: cada señal existe solo si hay un dato real detrás; la ausencia de un dato reduce la *cobertura* (nunca penaliza como "riesgo").

**Fuentes y URL**

| Dato | Fuente | URL | Licencia |
|---|---|---|---|
| Jerarquía de vía, velocidad máxima, iluminación, superficie, carril bici, cruces, calmado | OpenStreetMap vía Overpass API | `https://overpass-api.de/api/interpreter` (POST `[out:json]` con `around`) | ODbL 1.0 |
| Siniestralidad (sectores críticos con fallecidos, red vial nacional) | ANSV — "Sectores críticos de siniestralidad vial" (datos abiertos Colombia) | `https://www.datos.gov.co/resource/rs3u-8r4q.json` (SODA) | CC BY-SA 4.0 |

Sin API keys en el código; Overpass público sin autenticación (se respeta el rate limit con caché por corredor), SODA sin token para bajo volumen (`SAFETY_SOCRATA_APP_TOKEN` opcional).

**Algoritmo**

1. Muestrea la geometría de la ruta cada `SAFETY_OSM_SAMPLE_STEP_M` (60 m, tope `max_samples` 250).
2. Consulta Overpass por corredor (radio 60 m) y agrega, **por tramos reales**, highway → score, `maxspeed` → score, `lit`, `surface`, `cycleway`/`bicycle`, y densidades de cruces y calmado.
3. Cada factor aporta `peso × score real × cobertura`; el score final es el promedio ponderado de los factores disponibles: `score = Σ(weight × score) / Σ pesos disponibles`.
4. **Confianza** = `Σ(weight × cobertura) / Σ pesos habilitados`. Si el número de factores reales es `< SAFETY_MIN_FACTORS` (2) o la confianza `< SAFETY_MIN_CONFIDENCE` (0.25), el score es `null` (la UI avisa).
5. La siniestralidad (deshabilitada por defecto) solo pesa si el dataset registra sectores críticos cerca de la ruta (`count > 0`); con 0 registros el factor se **excluye** y nunca se concluye "seguro" por ausencia de datos.

**Pesos por factor** (`config/safety.php`): infraestructura ciclista 0.28, jerarquía de vía 0.22, velocidad 0.18, iluminación 0.10, superficie 0.08, siniestralidad 0.14. Los mapeos de puntuación (highway, maxspeed, lit, surface, cycleway) están documentados en el mismo archivo.

**Caché** (por corredor/bbox, TTL 24 h, configurables): `safety:osm:corridor:{bbox|step|radius|maxSamples}` y `safety:accidents:{dataset}:{bbox}`. El guard de producción es el array/memoria; se recomienda `redis` en despliegues.

**Fallback**: si Overpass falla, el proveedor devuelve un set vacío y registra la incidencia (la ruta conserva el resto de los segmentos y la UI muestra cobertura reducida). `SAFETY_ENABLED=false` inhabilita el score completo.

**Variables de entorno** (ver `.env.example`): `SAFETY_ENABLED`, `SAFETY_MIN_FACTORS`, `SAFETY_MIN_CONFIDENCE`, `SAFETY_OSM_ENABLED`, `SAFETY_OSM_OVERPASS_URL`, `SAFETY_OSM_CORRIDOR_RADIUS_M`, `SAFETY_OSM_SAMPLE_STEP_M`, `SAFETY_OSM_MAX_SAMPLES`, `SAFETY_OSM_TIMEOUT`, `SAFETY_OSM_CACHE_TTL`, `SAFETY_ACCIDENTS_ENABLED`, `SAFETY_ACCIDENTS_SOCRATA_*`.

**Mapa**: cada ruta muestra un chip de seguridad ("Seguridad X/100" o "Información de seguridad insuficiente") y, en la tarjeta seleccionada, la confianza del análisis, la explicación y la lista de señales con sus datos disponibles. (`mapa.blade.php`, `map.js`, `app.css`).

**Cobertura geográfica**: OpenStreetMap es mundial (Colombia con buena densidad, pero la cobertura real depende de lo que la comunidad haya mapeado — la ausencia de etiquetas se reporta como "sin dato"). El dataset ANSV documenta sectores críticos de la red vial nacional de carreteras, no calles urbanas: en rutas urbanas la siniestralidad siempre quedará excluida (honesto, no "seguro").

### Configuración

Variables de entorno (ver `.env.example`):

- `MAP_PROFILES` — lista separada por comas de perfiles ofrecidos (orden visible en la UI).
- `MAP_DEFAULT_PROFILE` — perfil preseleccionado (por defecto `fastest`).

### Tests

- `tests/Unit/RouteProfileRankerTest.php` — criterios de cada perfil.
- `tests/Unit/BicycleRoutingServiceTest.php` — `profiles()`/`routesForProfile()` sobre el driver falso.
- `tests/Unit/SafetyScoreServiceTest.php` — algoritmo (pesos, confianza, mínimos, habilitado/deshabilitado) con proveedores simulados.
- `tests/Unit/OpenStreetMapSafetyDataProviderTest.php` — señales reales desde Overpass simulado (tags, ausencias, errores y caché).
- `tests/Unit/SocrataAccidentDataProviderTest.php` — dataset SODA simulado (señal, saturación, deshabilitado).
- `tests/Feature/MapProfilesTest.php` — endpoint `/api/maps/profiles` y recalculación con perfil; incluye el fallback honesto (Overpass vacío → `safety_score: null`) y el caso real (señales → score 0-100).
