<?php

namespace App\Models;

use App\Enums\EstadoBicicleta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bicicleta extends Model
{
    use HasFactory;

    protected $table = 'bicicletas';

    protected $fillable = [
        'user_id',
        'tipo_bicicleta_id',
        'barrio_id',
        'marca',
        'modelo',
        'color',
        'numero_serie',
        'foto',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoBicicleta::class,
        ];
    }

    /**
     * La bicicleta pertenece a un usuario.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * La bicicleta pertenece a un tipo.
     */
    public function tipoBicicleta(): BelongsTo
    {
        return $this->belongsTo(TipoBicicleta::class);
    }

    /**
     * La bicicleta pertenece a un barrio.
     */
    public function barrio(): BelongsTo
    {
        return $this->belongsTo(Barrio::class);
    }
}
