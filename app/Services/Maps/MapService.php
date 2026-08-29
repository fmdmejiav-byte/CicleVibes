<?php

namespace App\Services\Maps;

use App\Enums\EstadoBicicleta;
use App\Models\Bicicleta;

/**
 * Servicio que agrupa la lógica de datos geográficos de CicleVibes:
 * marcadores de bicicletas, puntos de interés y coordenadas.
 */
class MapService
{
    /**
     * Devuelve los marcadores de bicicletas disponibles para el mapa.
     *
     * Las bicicletas se ubican usando las coordenadas de su barrio.
     * Solo se consideran bicicletas activas con barrio georreferenciado.
     *
     * @return array<int, array<string, mixed>>
     */
    public function bicicletasMarkers(): array
    {
        $limit = (int) config('services.map.bicicletas_limit', 100);

        $bicicletas = Bicicleta::query()
            ->with(['barrio', 'tipoBicicleta', 'user'])
            ->where('estado', EstadoBicicleta::Activa->value)
            ->whereHas('barrio', fn ($q) => $q->whereNotNull('latitude')->whereNotNull('longitude'))
            ->latest()
            ->limit($limit)
            ->get();

        return $bicicletas->map(function (Bicicleta $bicicleta) {
            $barrio = $bicicleta->barrio;

            return [
                'id' => $bicicleta->id,
                'latitude' => (float) $barrio->latitude,
                'longitude' => (float) $barrio->longitude,
                'marca' => $bicicleta->marca,
                'modelo' => $bicicleta->modelo,
                'color' => $bicicleta->color,
                'tipo' => $bicicleta->tipoBicicleta->nombre ?? 'Bicicleta',
                'barrio' => $barrio->nombre ?? '',
                'dueno' => $bicicleta->user?->nombre_completo ?? 'Usuario',
                'estado' => $bicicleta->estado->value,
            ];
        })->values()->all();
    }

    /**
     * Devuelve la configuración inicial del mapa.
     */
    public function config(): array
    {
        return [
            'tiles_url' => (string) config('services.map.tiles_url'),
            'tiles_attribution' => (string) config('services.map.tiles_attribution'),
            'tiles_max_zoom' => (int) config('services.map.tiles_max_zoom', 19),
            'default_lat' => (float) config('services.map.default_lat', 10.9871),
            'default_lng' => (float) config('services.map.default_lng', -74.7890),
            'default_zoom' => (int) config('services.map.default_zoom', 13),
            'nominatim_url' => (string) config('services.map.nominatim_url'),
            'osrm_url' => (string) config('services.map.osrm_url'),
            'osrm_profile' => (string) config('services.map.osrm_profile', 'cycling'),
            'routing_driver' => (string) config('services.map.routing_driver', 'osrm'),
            'alternatives_count' => (int) config('services.map.alternatives_count', 3),
        ];
    }
}
