<?php

namespace App\Services\Maps;

use Illuminate\Support\Facades\Http;

/**
 * Consulta infraestructura ciclista (ciclovías, ciclorutas, carriles bici)
 * almacenada en OpenStreetMap mediante Overpass API.
 *
 * Devuelve los trazados como GeoJSON para que Leaflet los dibuje en una
 * capa independiente e indicable. Responde a la vista actual del mapa
 * (bounding box) con consultas acotadas para respetar la política de uso
 * pública de Overpass.
 */
class OverpassService
{
    protected string $baseUrl;

    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.map.overpass_url'), '/');
        $this->timeout = (int) config('services.map.overpass_timeout', 25);
    }

    /**
     * Devuelve las infraestructuras ciclistas dentro de un bounding box
     * como un FeatureCollection GeoJSON.
     *
     *
     * @return array<string, mixed>
     */
    public function cyclorutas(float $minLat, float $minLng, float $maxLat, float $maxLng): array
    {
        $bbox = "{$minLat},{$minLng},{$maxLat},{$maxLng}";

        // Infraestructura específica para bicicletas en OpenStreetMap:
        // - Vías dedicadas a la bicicleta (ciclovías/ciclorutas): highway=cycleway
        // - Vías con carril o vía ciclista asociada: ciclo tag cycleway=*
        // - Vías marcadas para uso designado de bicicleta: bicycle=designated
        $query = '[out:json][timeout:'.$this->timeout.'];('.
            'way["highway"="cycleway"]('.$bbox.');'.
            'way["cycleway"]('.$bbox.');'.
            'way["bicycle"="designated"]('.$bbox.');'.
            ');out geom;';

        try {
            $response = Http::withHeaders([
                'User-Agent' => (string) config('services.map.user_agent'),
                'Accept' => 'application/json',
            ])->asForm()->timeout($this->timeout + 15)->post($this->baseUrl, ['data' => $query]);
        } catch (\Throwable $e) {
            return ['type' => 'FeatureCollection', 'features' => []];
        }

        if (! $response->successful()) {
            return ['type' => 'FeatureCollection', 'features' => []];
        }

        $elements = $response->json('elements') ?? [];
        $features = [];

        foreach ($elements as $element) {
            if (($element['type'] ?? '') !== 'way' || empty($element['geometry'])) {
                continue;
            }

            $coordinates = [];
            foreach ($element['geometry'] as $point) {
                $coordinates[] = [(float) $point['lon'], (float) $point['lat']];
            }

            if (count($coordinates) < 2) {
                continue;
            }

            $tags = $element['tags'] ?? [];
            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => $coordinates,
                ],
                'properties' => [
                    'highway' => $tags['highway'] ?? '',
                    'cycleway' => $tags['cycleway'] ?? '',
                    'name' => $tags['name'] ?? '',
                ],
            ];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }
}
