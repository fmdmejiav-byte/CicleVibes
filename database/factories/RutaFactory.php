<?php

namespace Database\Factories;

use App\Models\Ruta;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ruta>
 */
class RutaFactory extends Factory
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
            'nombre' => fake()->randomElement([
                'Ruta Parque Norte',
                'Vuelta al cerro',
                'Ciclovía al río',
                'Ruta de montaña',
                'Paseo por el centro',
                'Circuito miradores',
            ]),
            'descripcion' => fake()->optional()->sentence(),
            'origen' => fake()->streetAddress(),
            'destino' => fake()->streetAddress(),
            'distancia' => fake()->optional()->randomFloat(2, 1, 100),
            'duracion' => fake()->optional()->numberBetween(10, 300),
        ];
    }
}
