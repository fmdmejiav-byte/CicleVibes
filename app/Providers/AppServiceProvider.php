<?php

namespace App\Providers;

use App\Contracts\RouteProvider;
use App\Services\RouteProviders\GoogleDirectionsProvider;
use App\Services\Routing\BicycleRoutingDriver;
use App\Services\Routing\GraphHopperRoutingDriver;
use App\Services\Routing\OsrmRoutingDriver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RouteProvider::class, GoogleDirectionsProvider::class);

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
