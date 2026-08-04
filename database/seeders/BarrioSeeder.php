<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Barrio;

class BarrioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Barrio::insert([
            ['nombre' => 'Alto Prado', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'El Prado', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Boston', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Riomar', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Villa Carolina', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'La Concepción', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Las Delicias', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Ciudad Jardín', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Rebolo', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'La Manga', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'La Victoria', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Olaya', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'San Felipe', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Los Andes', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Miramar', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}