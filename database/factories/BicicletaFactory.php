<?php

namespace Database\Factories;

use App\Enums\EstadoBicicleta;
use App\Models\Barrio;
use App\Models\Bicicleta;
use App\Models\TipoBicicleta;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bicicleta>
 */
class BicicletaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tipo_bicicleta_id' => TipoBicicleta::factory(),
            'barrio_id' => Barrio::factory(),
            'marca' => fake()->randomElement(['Trek', 'Specialized', 'Giant', 'Scott', 'GW', 'Aeromax', 'Mercurio']),
            'modelo' => fake()->word(),
            'color' => fake()->colorName(),
            'numero_serie' => strtoupper(fake()->unique()->bothify('??????-########')),
            'foto' => null,
            'estado' => EstadoBicicleta::Activa,
        ];
    }
}
