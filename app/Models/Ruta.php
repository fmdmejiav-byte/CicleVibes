<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ruta extends Model
{
    protected $table = 'rutas';

    protected $fillable = [
        'user_id',
        'barrio_id',
        'nombre',
        'descripcion',
        'origen',
        'destino',
        'distancia',
    ];

    /**
     * La ruta pertenece a un usuario.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * La ruta pertenece a un barrio.
     */
    public function barrio(): BelongsTo
    {
        return $this->belongsTo(Barrio::class);
    }

    /**
     * Una ruta puede estar en muchos favoritos.
     */
    public function favoritos(): HasMany
    {
        return $this->hasMany(Favorito::class);
    }
}