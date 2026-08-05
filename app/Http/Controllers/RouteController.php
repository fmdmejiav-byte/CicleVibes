<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRutaRequest;
use App\Http\Requests\UpdateRutaRequest;
use App\Models\Ruta;

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

        return view('rutas.show', compact('ruta'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Ruta $ruta)
    {
        abort_if($ruta->user_id != auth()->id(), 403);

        return view('rutas.edit', compact('ruta'));
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
