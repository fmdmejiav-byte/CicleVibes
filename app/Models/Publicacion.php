<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Publicacion extends Model
{
    protected $table = 'publicaciones';

    protected $fillable = [
        'user_id',
        'contenido',
        'imagen',
        'estado',
    ];

    /**
     * La publicación pertenece a un usuario.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * La publicación tiene muchos comentarios.
     */
    public function comentarios(): HasMany
    {
        return $this->hasMany(Comentario::class);
    }
}