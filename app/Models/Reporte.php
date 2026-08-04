<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reporte extends Model
{
    protected $table = 'reportes';

    protected $fillable = [
        'user_id',
        'tipo_reporte_id',
        'barrio_id',
        'titulo',
        'descripcion',
        'latitud',
        'longitud',
        'estado',
    ];

    /**
     * El reporte pertenece a un usuario.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * El reporte pertenece a un tipo de reporte.
     */
    public function tipoReporte(): BelongsTo
    {
        return $this->belongsTo(TipoReporte::class);
    }

    /**
     * El reporte pertenece a un barrio.
     */
    public function barrio(): BelongsTo
    {
        return $this->belongsTo(Barrio::class);
    }
}