<?php

namespace Database\Seeders;

use App\Models\Rol;
use Illuminate\Database\Seeder;

class RolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Rol::updateOrCreate(['nombre' => 'Administrador']);
        Rol::updateOrCreate(['nombre' => 'Usuario']);

        Rol::where('nombre', 'Ciclista')->delete();
    }
}
