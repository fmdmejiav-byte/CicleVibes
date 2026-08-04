<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TipoBicicleta;

class TipoBicicletaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TipoBicicleta::insert([
            ['nombre' => 'Montaña', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Ruta', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'BMX', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Urbana', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Eléctrica', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}