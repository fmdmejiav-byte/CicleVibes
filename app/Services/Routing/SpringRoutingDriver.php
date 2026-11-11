<?php

namespace App\Services\Routing;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SpringRoutingDriver implements BicycleRoutingDriver
{
    public function name(): string
    {
        return 'osrm';
    }

    public function routes(array $origin, array $destination, ?int $alternatives = null): ?array
    {
        $url = rtrim(config('services.map.spring_routing_url'), '/').'/internal/routes';
        $count = $alternatives ?? config('services.map.osrm_alternatives', 2);

        try {
            $response = Http::acceptJson()->timeout(28)->get($url, [
                'origin_lat' => $origin['lat'],
                'origin_lng' => $origin['lng'],
                'dest_lat' => $destination['lat'],
                'dest_lng' => $destination['lng'],
                'alternatives' => min(3, max(0, $count)),
            ]);
        } catch (ConnectionException) {
            return null;
        }

        $routes = $response->successful() ? $response->json('routes') : null;

        return is_array($routes) && array_is_list($routes) && $routes !== [] ? $routes : null;
    }
}
