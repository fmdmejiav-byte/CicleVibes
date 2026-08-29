<?php

namespace App\Services\Maps;

use Illuminate\Support\Facades\Http;

/**
 * Servicio de geocodificación basado en Nominatim (OpenStreetMap).
 *
 * Respeta la política de uso pública de Nominatim:
 * - Máximo 1 petición por segundo.
 * - Se envía un User-Agent identificativo de la aplicación.
 * - No se realizan peticiones automáticas por cada tecla presionada
 *   (el frontend aplica debounce).
 */
class GeocodingService
{
    protected string $baseUrl;

    protected string $userAgent;

    protected int $limit;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.map.nominatim_url'), '/');
        $this->userAgent = (string) config('services.map.user_agent');
        $this->limit = (int) config('services.map.search_limit', 6);
    }

    /**
     * Busca lugares por texto libre.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query): array
    {
        if (trim($query) === '') {
            return [];
        }

        $response = Http::withHeaders([
            'User-Agent' => $this->userAgent,
            'Accept' => 'application/json',
        ])->timeout(10)->get($this->baseUrl.'/search', [
            'q' => $query,
            'format' => 'jsonv2',
            'limit' => $this->limit,
            'addressdetails' => 1,
        ]);

        if (! $response->successful()) {
            return [];
        }

        $results = $response->json();

        $places = [];
        foreach ((array) $results as $result) {
            $places[] = [
                'place_id' => $result['place_id'] ?? null,
                'name' => $result['name'] ?? ($result['display_name'] ?? ''),
                'address' => $result['display_name'] ?? '',
                'latitude' => (float) ($result['lat'] ?? 0),
                'longitude' => (float) ($result['lon'] ?? 0),
                'type' => $result['addresstype'] ?? ($result['type'] ?? ''),
            ];
        }

        return $places;
    }
}
