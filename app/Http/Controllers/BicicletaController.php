<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBicicletaRequest;
use App\Http\Requests\UpdateBicicletaRequest;
use App\Models\Barrio;
use App\Models\Bicicleta;
use App\Models\TipoBicicleta;

class BicicletaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $bicicletas = Bicicleta::where('user_id', auth()->id())
            ->latest()
            ->get();

        return view('bicicletas.index', compact('bicicletas'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
{
    $tipos = TipoBicicleta::all();
    $barrios = Barrio::all();

    return view('bicicletas.create', compact('tipos', 'barrios'));
}

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBicicletaRequest $request)
{
    Bicicleta::create([
        'user_id' => auth()->id(),
        'tipo_bicicleta_id' => $request->tipo_bicicleta_id,
        'barrio_id' => $request->barrio_id,
        'marca' => $request->marca,
        'modelo' => $request->modelo,
        'color' => $request->color,
        'numero_serie' => $request->numero_serie,
        'foto' => null,
        'estado' => 'Activa',
    ]);

    return redirect()
        ->route('bicicletas.index')
        ->with('success', 'Bicicleta registrada correctamente.');
}
    /**
     * Display the specified resource.
     */
    public function show(Bicicleta $bicicleta)
    {
        abort_if($bicicleta->user_id != auth()->id(), 403);

        return view('bicicletas.show', compact('bicicleta'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Bicicleta $bicicleta)
    {
        abort_if($bicicleta->user_id != auth()->id(), 403);

        $tipos = TipoBicicleta::all();
        $barrios = Barrio::all();

        return view('bicicletas.edit', compact('bicicleta', 'tipos', 'barrios'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBicicletaRequest $request, Bicicleta $bicicleta)
    {
        abort_if($bicicleta->user_id != auth()->id(), 403);

        $bicicleta->update([
            'tipo_bicicleta_id' => $request->tipo_bicicleta_id,
            'barrio_id' => $request->barrio_id,
            'marca' => $request->marca,
            'modelo' => $request->modelo,
            'color' => $request->color,
            'numero_serie' => $request->numero_serie,
        ]);

        return redirect()
            ->route('bicicletas.show', $bicicleta)
            ->with('success', 'Bicicleta actualizada correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Bicicleta $bicicleta)
    {
        abort_if($bicicleta->user_id != auth()->id(), 403);

        $bicicleta->delete();

        return redirect()
            ->route('bicicletas.index')
            ->with('success', 'Bicicleta eliminada correctamente.');
    }

}