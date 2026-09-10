<?php

namespace App\Services\Routing;

use App\Contracts\SafetyScoringService;
use App\Enums\RouteProfile;

/**
 * Capa de criterios del planificador inteligente de rutas.
 *
 * Recibe las candidatas REALES calculadas por el motor común de rutas
 * (OSRM/GraphHopper + red de ciclorrutas) y las ordena según el perfil
 * seleccionado. Es la única pieza que traduce un perfil en una elección.
 *
 * Reglas:
 *  - Nunca fabrica datos: si un campo no existe se omite y se usa el
 *    siguiente criterio real disponible, o se documenta la limitación.
 *  - FASTEST  → menor duración real (el motor ciclista ya evita vías
 *               incompatibles con bicicleta).
 *  - SHORTEST → menor distancia real, con las restricciones de bicicleta
 *               respetadas por el motor.
 *  - EASIEST  → menos desnivel positivo si el proveedor entrega elevación
 *               real; si no, usa la ruta ciclista del motor (dato real) y
 *               la limitación queda documentada.
 *  - SCENIC   → mayor cobertura real de infraestructura ciclista (menor
 *               exposición al tráfico), desempate por duración.
 *  - SAFEST   → mayor Safety Score real (0-100) cuando existe (datos
 *               verificables suficientes); si la información es insuficiente
 *               se ordena por la señal real de infraestructura ciclista.
 */
class RouteProfileRanker
{
    public function __construct(
        protected SafetyScoringService $safety,
    ) {}

    /**
     * Ordena las candidatas según el perfil (mejor primera).
     *
     * @param  array<int, array<string, mixed>>  $routes
     * @return array<int, array<string, mixed>>
     */
    public function rank(string|RouteProfile $profile, array $routes): array
    {
        $profile = RouteProfile::tryFromMixed($profile);
        if ($profile === null || $routes === []) {
            return $routes;
        }

        $sorted = $routes;

        match ($profile) {
            RouteProfile::Fastest => $this->sortFastest($sorted),
            RouteProfile::Shortest => $this->sortShortest($sorted),
            RouteProfile::Easiest => $this->sortEasiest($sorted),
            RouteProfile::Scenic => $this->sortScenic($sorted),
            RouteProfile::Safest => $this->sortSafest($sorted),
        };

        return $sorted;
    }

    /**
     * Devuelve la mejor candidata según el perfil, o null si no hay ninguna.
     *
     * @param  array<int, array<string, mixed>>  $routes
     * @return array<string, mixed>|null
     */
    public function preferred(string|RouteProfile $profile, array $routes): ?array
    {
        $sorted = $this->rank($profile, $routes);

        return $sorted[0] ?? null;
    }

    /**
     * Acceso al servicio de seguridad (para metadatos).
     */
    public function safety(): SafetyScoringService
    {
        return $this->safety;
    }

    protected function sortFastest(array &$routes): void
    {
        usort($routes, function (array $a, array $b): int {
            $cmp = $this->num($a, 'duration_seconds') <=> $this->num($b, 'duration_seconds');

            return $cmp !== 0
                ? $cmp
                : ($this->num($a, 'distance_m') <=> $this->num($b, 'distance_m'));
        });
    }

    protected function sortShortest(array &$routes): void
    {
        usort($routes, function (array $a, array $b): int {
            $cmp = $this->num($a, 'distance_m') <=> $this->num($b, 'distance_m');

            return $cmp !== 0
                ? $cmp
                : ($this->num($a, 'duration_seconds') <=> $this->num($b, 'duration_seconds'));
        });
    }

    protected function sortEasiest(array &$routes): void
    {
        usort($routes, function (array $a, array $b): int {
            $hasElevationA = $this->hasElevation($a);
            $hasElevationB = $this->hasElevation($b);

            // Las candidatas con elevación real van primero: así el criterio
            // jamás descarta datos reales por la mera ausencia de otros.
            if ($hasElevationA !== $hasElevationB) {
                return $hasElevationA ? -1 : 1;
            }

            if ($hasElevationA) {
                $cmp = $this->num($a, 'ascent_m') <=> $this->num($b, 'ascent_m');
                if ($cmp !== 0) {
                    return $cmp;
                }

                $cmp = $this->num($a, 'slope_pct') <=> $this->num($b, 'slope_pct');
                if ($cmp !== 0) {
                    return $cmp;
                }
            }

            // Sin datos de elevación reales: el motor ciclista ya evita las
            // calles más empinadas según OpenStreetMap, así que la duración
            // real es el mejor proxy disponible. La limitación se documenta.
            $cmp = $this->num($a, 'duration_seconds') <=> $this->num($b, 'duration_seconds');

            return $cmp !== 0
                ? $cmp
                : ($this->num($a, 'distance_m') <=> $this->num($b, 'distance_m'));
        });
    }

    protected function sortScenic(array &$routes): void
    {
        usort($routes, function (array $a, array $b): int {
            // Mayor cobertura de infraestructura ciclista real primero.
            $cmp = $this->int($b, 'cicloruta_coverage_pct') <=> $this->int($a, 'cicloruta_coverage_pct');
            if ($cmp !== 0) {
                return $cmp;
            }

            $cmp = $this->num($a, 'duration_seconds') <=> $this->num($b, 'duration_seconds');

            return $cmp !== 0
                ? $cmp
                : ($this->num($a, 'distance_m') <=> $this->num($b, 'distance_m'));
        });
    }

    protected function sortSafest(array &$routes): void
    {
        // El Safety Score (0-100) ya viene precalculado en metadata.safety_score
        // por BicycleRoutingService (assessment memoizado). Solo se recalcula
        // aquí si las candidatas llegan sin metadatos (tests/uso aislado).
        $anyScore = false;
        foreach ($routes as &$route) {
            $score = $route['metadata']['safety_score'] ?? $this->safety->score($route);
            if ($score !== null) {
                $anyScore = true;
            }
            $route['safety_score'] = $score;
        }
        unset($route);

        usort($routes, function (array $a, array $b) use ($anyScore) {
            if ($anyScore) {
                $scoreA = $a['safety_score'] ?? null;
                $scoreB = $b['safety_score'] ?? null;

                if ($scoreA !== null || $scoreB !== null) {
                    // Mayor seguridad primero; las rutas sin score (insuficiente)
                    // quedan al final del grupo.
                    return ($scoreB ?? -1.0) <=> ($scoreA ?? -1.0);
                }
            }

            // Fallback honesto (sin Safety Score): máxima cobertura real de
            // infraestructura ciclista y, en desempate, mayor índice real de
            // infraestructura protegida.
            $cmp = $this->int($b, 'cicloruta_coverage_pct') <=> $this->int($a, 'cicloruta_coverage_pct');
            if ($cmp !== 0) {
                return $cmp;
            }

            $cmp = $this->num($b, 'cicloruta_safety_index') <=> $this->num($a, 'cicloruta_safety_index');
            if ($cmp !== 0) {
                return $cmp;
            }

            $cmp = $this->num($a, 'duration_seconds') <=> $this->num($b, 'duration_seconds');

            return $cmp !== 0
                ? $cmp
                : ($this->num($a, 'distance_m') <=> $this->num($b, 'distance_m'));
        });

        // Limpia el campo temporal usado solo para ordenar.
        foreach ($routes as &$route) {
            unset($route['safety_score']);
        }
        unset($route);
    }

    /**
     * ¿La ruta trae elevación real del proveedor?
     *
     * @param  array<string, mixed>  $route
     */
    protected function hasElevation(array $route): bool
    {
        return ($route['elevation_available'] ?? false) === true
            || (isset($route['ascent_m']) && is_numeric($route['ascent_m']));
    }

    /**
     * Número real de una clave; si no existe se devuelve PHP_FLOAT_MAX para
     * que la candidata quede al final en criterios "menor es mejor".
     *
     * @param  array<string, mixed>  $route
     */
    protected function num(array $route, string $key): float
    {
        $value = $route[$key] ?? null;

        return is_numeric($value) ? (float) $value : PHP_FLOAT_MAX;
    }

    /**
     * Entero de una clave con 0 por defecto (criterios "mayor es mejor").
     *
     * @param  array<string, mixed>  $route
     */
    protected function int(array $route, string $key): int
    {
        $value = $route[$key] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }
}