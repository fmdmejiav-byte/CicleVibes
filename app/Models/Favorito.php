<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Favorito extends Model
{
    protected $table = 'favoritos';

    protected $fillable = [
        'user_id',
        'ruta_id',
    ];

    /**
     * El favorito pertenece a un usuario.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * El favorito pertenece a una ruta.
     */
    public function ruta(): BelongsTo
    {
        return $this->belongsTo(Ruta::class);
    }
}