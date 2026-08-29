<?php

namespace Database\Seeders;

use App\Models\Barrio;
use Illuminate\Database\Seeder;

class BarrioSeeder extends Seeder
{
    /**
     * Barrios de Barranquilla con coordenadas aproximadas de su zona
     * para poder ubicarlos en el mapa.
     */
    private array $barrios = [
        ['Alto Prado' => [11.0190, -74.8260]],
        ['El Prado' => [11.0020, -74.8230]],
        ['Boston' => [10.9970, -74.8170]],
        ['Riomar' => [11.0250, -74.8500]],
        ['Villa Carolina' => [11.0320, -74.8560]],
        ['La Concepción' => [10.9860, -74.8100]],
        ['Las Delicias' => [11.0000, -74.8490]],
        ['Ciudad Jardín' => [10.9820, -74.7720]],
        ['Rebolo' => [10.9670, -74.7930]],
        ['La Manga' => [10.9710, -74.8010]],
        ['La Victoria' => [10.9900, -74.7840]],
        ['Olaya' => [10.9600, -74.7600]],
        ['San Felipe' => [10.9680, -74.8150]],
        ['Los Andes' => [10.9770, -74.8210]],
        ['Miramar' => [11.0160, -74.8470]],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->barrios as $barrio) {
            $nombre = array_key_first($barrio);
            [$lat, $lng] = $barrio[$nombre];

            Barrio::updateOrCreate(
                ['nombre' => $nombre],
                ['latitude' => $lat, 'longitude' => $lng]
            );
        }
    }
}
