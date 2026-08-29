<?php

namespace App\Console\Commands;

use App\Services\Maps\CiclorutaService;
use Illuminate\Console\Command;

/**
 * Auditoría de la red de ciclorrutas de Barranquilla.
 *
 * Genera un reporte de calidad y cobertura de resources/data/
 * ciclorutas-barranquilla.geojson (features, km por tipo, componentes,
 * corredores, duplicados, segmentos cortos, aislados y huecos), y lo guarda
 * en storage/app/ciclorutas-audit.json.
 *
 * El reporte incluye las conexiones "sospechosas" (20-50 m) que NO se unen
 * automáticamente para que queden registradas para revisión humana.
 */
class CiclorutasAudit extends Command
{
    protected $signature = 'ciclorutas:audit
        {--file= : Ruta alternativa al GeoJSON (por defecto la configurada)}';

    protected $description = 'Audita la red de ciclorrutas y escribe storage/app/ciclorutas-audit.json';

    /**
     * Corredores de referencia del mapa oficial de ciclorrutas de Barranquilla
     * (83.10 km, PET 2022). Se usan SOLO para comparar contra lo que existe en
     * OSM. Cuando no hay coordenadas suficientes se marcan como
     * "pendiente de georreferenciación" (el PDF es una imagen escaneada; no se
     * inventan geometrías).
     *
     * @var array<int, array<string, string>>
     */
    protected array $referenceCorridors = [
        ['name' => 'Troncal del Caribe', 'type' => 'ciclorruta_calzada'],
        ['name' => 'Ciclovía Las Flores - Puerto Mocho', 'type' => 'ciclorruta_calzada'],
        ['name' => 'Vía 17 de Diciembre (La Playa)', 'type' => 'ciclorruta_calzada'],
        ['name' => 'Avenida de la Marina (Carrera 43-44)', 'type' => 'ciclorruta_calzada'],
        ['name' => 'Avenida de Los Estudiantes (Carrera 14)', 'type' => 'ciclorruta_calzada'],
        ['name' => 'Carrera 50', 'type' => 'ciclobanda'],
        ['name' => 'Carrera 47', 'type' => 'ciclobanda'],
        ['name' => 'Calle 47 (Alfonso López)', 'type' => 'carril_ciclo_preferente'],
        ['name' => 'Carrera 46 (Corredor Universitario)', 'type' => 'carril_ciclo_preferente'],
        ['name' => 'Puerto Colombia', 'type' => 'ciclorruta_calzada'],
        ['name' => 'Soledad / Malambo', 'type' => 'ciclorruta_calzada'],
    ];

    public function handle(CiclorutaService $service): int
    {
        $this->line('Auditando red de ciclorrutas…');

        $report = $service->auditReport();
        $report['reference_corridors'] = $this->compareReferenceCorridors($service, $report['corridors'] ?? []);

        $path = storage_path('app/ciclorutas-audit.json');
        $this->ensureDir(dirname($path));
        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $s = $report['summary'];

        $this->info('--- Resumen de la auditoría ---');
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Features', $s['features_count']],
                ['Total km', $s['total_km']],
                ['km por tipo', json_encode($s['by_type_km'], JSON_UNESCAPED_UNICODE)],
                ['Referencia oficial km', $s['official_reference_total_km'] ?? 'n/d'],
                ['Componentes (topología bruta)', $s['components_before_join']],
                ['Componentes (con unión ≤20 m)', $s['components_after_join']],
                ['Conexiones automáticas (<20 m)', $s['auto_connected_gaps_lt_20m']],
                ['Conexiones sospechosas (20-50 m)', $s['suspicious_gaps_20_50m']],
                ['Segmentos duplicados', $s['duplicate_segments']],
                ['Segmentos cortos (<20 m)', $s['short_segments_lt_20m']],
                ['Segmentos aislados', $s['isolated_segments']],
            ]
        );

        $this->newLine();
        $this->info('Reporte completo: '.$path);

        $this->newLine();
        $this->comment('Corredores de referencia vs datos OSM:');
        foreach ($report['reference_corridors'] as $ref) {
            $status = $ref['status'];
            $extra = isset($ref['osm_km']) ? " ({$ref['osm_km']} km en OSM)" : '';
            $this->line(" • {$ref['name']} => {$status}{$extra}");
        }

        return self::SUCCESS;
    }

    /**
     * Compara los corredores de referencia del mapa oficial con lo que existe
     * de verdad en la red (una superposición aproximada por nombre; no se
     * inventan geometrías).
     *
     * @param  array<int, array<string, mixed>>  $corridors
     * @return array<int, array<string, mixed>>
     */
    protected function compareReferenceCorridors(CiclorutaService $service, array $corridors): array
    {
        $index = [];
        foreach ($corridors as $c) {
            $key = mb_strtolower(trim($c['name']));
            $index[$key] = $c;
        }

        $result = [];
        foreach ($this->referenceCorridors as $ref) {
            $found = false;
            foreach ($index as $key => $c) {
                // Coincidencia por nombre o por calle en el nombre del corredor.
                $needle = mb_strtolower($ref['name']);
                if (str_contains($key, $needle) || str_contains($needle, $key)) {
                    $result[] = [
                        'name' => $ref['name'],
                        'osm_match' => $c['name'],
                        'osm_type' => $c['type'],
                        'osm_km' => $c['km'],
                        'status' => 'parcial_en_osm',
                    ];
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $result[] = [
                    'name' => $ref['name'],
                    'status' => 'pendiente_georreferenciación',
                    'reason' => 'no hay geometría suficiente en OSM; el PDF oficial es una imagen escaneada y no se inventan coordenadas',
                ];
            }
        }

        return $result;
    }

    /**
     * Crea el directorio si no existe.
     */
    protected function ensureDir(string $dir): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
