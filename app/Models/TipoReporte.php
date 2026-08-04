<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoReporte extends Model
{
    protected $table = 'tipo_reportes';

    protected $fillable = [
        'nombre',
    ];

    /**
     * Un tipo de reporte puede tener muchos reportes.
     */
    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class);
    }
}