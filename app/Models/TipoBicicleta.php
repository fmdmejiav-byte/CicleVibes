<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoBicicleta extends Model
{
    use HasFactory;

    protected $table = 'tipo_bicicletas';

    protected $fillable = [
        'nombre',
    ];

    /**
     * Un tipo de bicicleta puede tener muchas bicicletas.
     */
    public function bicicletas(): HasMany
    {
        return $this->hasMany(Bicicleta::class);
    }
}
