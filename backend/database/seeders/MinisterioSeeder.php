<?php

namespace Database\Seeders;

use App\Models\Ministerio;
use Illuminate\Database\Seeder;

class MinisterioSeeder extends Seeder
{
    public function run(): void
    {
        $ministerios = [
            [
                'codigo' => 'MS',
                'nombre' => 'Ministerio de Salud',
                'sigla' => 'MS',
                'orden_prioridad' => 1,
                'activo' => true,
            ],
            [
                'codigo' => 'ME',
                'nombre' => 'Ministerio de Educación',
                'sigla' => 'ME',
                'orden_prioridad' => 2,
                'activo' => true,
            ],
            [
                'codigo' => 'MEYF',
                'nombre' => 'Ministerio de Economía y Finanzas',
                'sigla' => 'MEYF',
                'orden_prioridad' => 3,
                'activo' => true,
            ],
            [
                'codigo' => 'MYS',
                'nombre' => 'Ministerio de Seguridad',
                'sigla' => 'MYS',
                'orden_prioridad' => 4,
                'activo' => true,
            ],
            [
                'codigo' => 'MISP',
                'nombre' => 'Ministerio de Infraestructura y Servicios Públicos',
                'sigla' => 'MISP',
                'orden_prioridad' => 5,
                'activo' => true,
            ],
            [
                'codigo' => 'MGG',
                'nombre' => 'Ministerio de Gobierno y Justicia',
                'sigla' => 'MGYJ',
                'orden_prioridad' => 6,
                'activo' => true,
            ],
        ];

        foreach ($ministerios as $m) {
            Ministerio::updateOrCreate(['codigo' => $m['codigo']], $m);
        }
    }
}
