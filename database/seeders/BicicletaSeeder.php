<?php

namespace Database\Seeders;

use App\Models\Barrio;
use App\Models\Bicicleta;
use App\Models\Rol;
use App\Models\TipoBicicleta;
use App\Models\User;
use Illuminate\Database\Seeder;

class BicicletaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rolUsuario = Rol::where('nombre', 'Usuario')->firstOrFail();

        $tipos = TipoBicicleta::pluck('id')->all();
        $barrios = Barrio::pluck('id')->all();

        if (empty($tipos)) {
            $tipos = TipoBicicleta::factory()->count(5)->create()->pluck('id')->all();
        }

        if (empty($barrios)) {
            $barrios = Barrio::factory()->count(5)->create()->pluck('id')->all();
        }

        $usuarios = User::factory()->count(10)->create([
            'rol_id' => $rolUsuario->id,
        ]);

        foreach ($usuarios as $usuario) {
            Bicicleta::factory()
                ->count(random_int(1, 3))
                ->create([
                    'user_id' => $usuario->id,
                    'tipo_bicicleta_id' => fake()->randomElement($tipos),
                    'barrio_id' => fake()->randomElement($barrios),
                ]);
        }
    }
}
