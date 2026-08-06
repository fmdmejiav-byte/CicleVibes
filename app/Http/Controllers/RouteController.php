<?php

namespace App\Http\Controllers;

use App\Exceptions\RouteCalculationException;
use App\Http\Requests\StoreRutaRequest;
use App\Http\Requests\UpdateRutaRequest;
use App\Models\Ruta;
use App\Services\BikeRouteService;
use App\Support\Polyline;
use Illuminate\Http\Request;

class RouteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $rutas = Ruta::where('user_id', auth()->id())
            ->latest()
            ->get();

        return view('rutas.index', compact('rutas'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('rutas.create');
    }

    /**
     * Calcula una ruta en bicicleta a través del BikeRouteService.
     *
     * El frontend nunca llama a Google Directions directamente: consume este
     * endpoint y dibuja la geometría devuelta. Así el algoritmo futuro puede
     * reemplazar o modificar la ruta sin tocar la vista.
     */
    public function calcular(Request $request, BikeRouteService $bikeRouteService)
    {
        $datos = $request->validate([
            'origen' => ['required', 'string', 'max:255'],
            'destino' => ['required', 'string', 'max:255'],
        ]);

        try {
            $resultado = $bikeRouteService->calculate($datos['origen'], $datos['destino']);
        } catch (RouteCalculationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'polyline' => $resultado->polyline,
            'distance' => $resultado->distance,
            'duration' => $resultado->duration,
            'coordinates' => $resultado->coordinates,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRutaRequest $request)
    {
        Ruta::create([
            'user_id' => auth()->id(),
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'origen' => $request->origen,
            'destino' => $request->destino,
            'distancia' => $request->distancia,
            'duracion' => $request->duracion,
            'polilinea' => $request->polilinea,
        ]);

        return redirect()
            ->route('rutas.index')
            ->with('success', 'Ruta creada correctamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Ruta $ruta)
    {
        abort_if($ruta->user_id != auth()->id(), 403);

        $coordenadasRuta = $ruta->polilinea ? Polyline::decode($ruta->polilinea) : [];

        return view('rutas.show', compact('ruta', 'coordenadasRuta'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Ruta $ruta)
    {
        abort_if($ruta->user_id != auth()->id(), 403);

        $coordenadasRuta = $ruta->polilinea ? Polyline::decode($ruta->polilinea) : [];

        return view('rutas.edit', compact('ruta', 'coordenadasRuta'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRutaRequest $request, Ruta $ruta)
    {
        abort_if($ruta->user_id != auth()->id(), 403);

        $ruta->update([
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'origen' => $request->origen,
            'destino' => $request->destino,
            'distancia' => $request->distancia,
            'duracion' => $request->duracion,
            'polilinea' => $request->polilinea,
        ]);

        return redirect()
            ->route('rutas.show', $ruta)
            ->with('success', 'Ruta actualizada correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Ruta $ruta)
    {
        abort_if($ruta->user_id != auth()->id(), 403);

        $ruta->delete();

        return redirect()
            ->route('rutas.index')
            ->with('success', 'Ruta eliminada correctamente.');
    }
}
