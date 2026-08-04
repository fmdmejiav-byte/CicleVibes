<?php

namespace Database\Factories;

use App\Models\Barrio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Barrio>
 */
class BarrioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->city(),
        ];
    }
}
