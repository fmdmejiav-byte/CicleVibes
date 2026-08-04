<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barrio extends Model
{
    use HasFactory;

    protected $table = 'barrios';

    protected $fillable = [
        'nombre',
    ];

    /**
     * Un barrio puede tener muchas bicicletas.
     */
    public function bicicletas(): HasMany
    {
        return $this->hasMany(Bicicleta::class);
    }

    /**
     * Un barrio puede tener muchos reportes.
     */
    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class);
    }

    /**
     * Un barrio puede tener muchas rutas.
     */
    public function rutas(): HasMany
    {
        return $this->hasMany(Ruta::class);
    }
}
