<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\Ruta;
use App\Models\User;
use Illuminate\Database\Seeder;

class RutaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rolUsuario = Rol::where('nombre', 'Usuario')->firstOrFail();

        $usuarios = User::where('rol_id', $rolUsuario->id)->get();

        if ($usuarios->isEmpty()) {
            $usuarios = User::factory()->count(10)->create([
                'rol_id' => $rolUsuario->id,
            ]);
        }

        foreach ($usuarios as $usuario) {
            Ruta::factory()
                ->count(random_int(1, 4))
                ->create([
                    'user_id' => $usuario->id,
                ]);
        }
    }
}
