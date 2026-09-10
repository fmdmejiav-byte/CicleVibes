<?php

namespace App\Providers;

use App\Contracts\AccidentDataProvider;
use App\Contracts\RouteProvider;
use App\Contracts\SafetyDataProvider;
use App\Contracts\SafetyScoringService;
use App\Services\RouteProviders\GoogleDirectionsProvider;
use App\Services\Routing\BicycleRoutingDriver;
use App\Services\Routing\GraphHopperRoutingDriver;
use App\Services\Routing\OsrmRoutingDriver;
use App\Services\Routing\Safety\OpenStreetMapSafetyDataProvider;
use App\Services\Routing\Safety\SafetyScoreService;
use App\Services\Routing\Safety\SocrataAccidentDataProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RouteProvider::class, GoogleDirectionsProvider::class);

        // Abstracción de seguridad para rutas (fase 2).
        // La implementación activa es el Safety Score real (0-100) con datos
        // verificables: OpenStreetMap (Overpass), red local de ciclorrutas y,
        // si se habilita, el dataset oficial de siniestralidad. Nunca inventa
        // puntuaciones; sin datos suficientes devuelve score null.
        $this->app->bind(SafetyDataProvider::class, OpenStreetMapSafetyDataProvider::class);
        $this->app->bind(AccidentDataProvider::class, SocrataAccidentDataProvider::class);
        $this->app->bind(SafetyScoringService::class, SafetyScoreService::class);

        // Resuelve el motor de rutas para bicicletas según la configuración.
        // Por defecto OSRM (sin API key); se puede cambiar a GraphHopper
        // configurando MAP_ROUTING_DRIVER=graphhopper y una clave real.
        if ((string) config('services.map.routing_driver') === 'graphhopper') {
            $this->app->bind(BicycleRoutingDriver::class, GraphHopperRoutingDriver::class);
        } else {
            $this->app->bind(BicycleRoutingDriver::class, OsrmRoutingDriver::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
