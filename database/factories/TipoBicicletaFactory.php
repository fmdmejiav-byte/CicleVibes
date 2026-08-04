<?php

namespace Database\Factories;

use App\Models\TipoBicicleta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoBicicleta>
 */
class TipoBicicletaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->randomElement(['Montaña', 'Ruta', 'BMX', 'Urbana', 'Eléctrica']),
        ];
    }
}
