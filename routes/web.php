<?php

use App\Http\Controllers\BicicletaController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RouteController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {

    Route::post('/rutas/calcular', [RouteController::class, 'calcular'])->name('rutas.calcular');

    Route::resource('bicicletas', BicicletaController::class);

    Route::resource('rutas', RouteController::class);

    // Mapa / Navegación de CicleVibes (Leaflet + OpenStreetMap)
    Route::get('/mapa', [MapController::class, 'index'])->name('mapa');
    Route::get('/api/maps/search', [MapController::class, 'search'])->name('maps.search')
        ->middleware(['throttle:30,1']);
    Route::get('/api/maps/route', [MapController::class, 'route'])->name('maps.route')
        ->middleware(['throttle:30,1']);
    Route::get('/api/maps/alternatives', [MapController::class, 'alternatives'])->name('maps.alternatives')
        ->middleware(['throttle:30,1']);
    Route::get('/api/maps/profiles', [MapController::class, 'profiles'])->name('maps.profiles')
        ->middleware(['throttle:30,1']);
    Route::get('/api/maps/recalculate', [MapController::class, 'recalculate'])->name('maps.recalculate')
        ->middleware(['throttle:20,1']);
    Route::get('/api/maps/cyclorutas', [MapController::class, 'cyclorutas'])->name('maps.cyclorutas')
        ->middleware(['throttle:15,1']);
    Route::get('/api/maps/bicicletas', [MapController::class, 'bicicletas'])->name('maps.bicicletas');

    // Red de ciclorrutas de CicleVibes (GeoJSON local) y su punto más cercano.
    Route::get('/api/ciclorutas', [MapController::class, 'ciclorutas'])->name('maps.ciclorutas')
        ->middleware(['throttle:15,1']);
    Route::get('/api/ciclorutas/nearest', [MapController::class, 'ciclorutasNearest'])->name('maps.ciclorutas.nearest')
        ->middleware(['throttle:30,1']);

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

});

require __DIR__.'/auth.php';
