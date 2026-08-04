<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TipoReporte;

class TipoReporteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TipoReporte::insert([
            ['nombre' => 'Robo', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Accidente', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Hueco', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Inundación', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Alumbrado dañado', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Cierre de vía', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}