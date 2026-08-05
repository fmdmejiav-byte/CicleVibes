<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ruta extends Model
{
    use HasFactory;

    protected $table = 'rutas';

    protected $fillable = [
        'user_id',
        'nombre',
        'descripcion',
        'origen',
        'destino',
        'distancia',
        'duracion',
    ];

    /**
     * La ruta pertenece a un usuario.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Una ruta puede estar en muchos favoritos.
     */
    public function favoritos(): HasMany
    {
        return $this->hasMany(Favorito::class);
    }
}
