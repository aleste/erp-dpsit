<?php

namespace Database\Seeders;

use App\Models\AreaTi;
use App\Models\Ministerio;
use Illuminate\Database\Seeder;

class AreaTiSeeder extends Seeder
{
    public function run(): void
    {
        $ms = Ministerio::where('codigo', 'MS')->first();
        $me = Ministerio::where('codigo', 'ME')->first();
        $meyf = Ministerio::where('codigo', 'MEYF')->first();
        $mys = Ministerio::where('codigo', 'MYS')->first();
        $misp = Ministerio::where('codigo', 'MISP')->first();

        $areas = [
            [
                'ministerio_id' => $ms->id,
                'codigo' => 'TI-MS-01',
                'nombre' => 'Dirección General de Tecnologías de la Información en Salud',
                'nivel_jerarquico' => 'DIRECCION_GENERAL',
                'es_homologada' => true,
                'resolucion_homologacion' => 'RES-2023-142-DPSIT',
                'domicilio_fisico' => 'Calle 51 N° 1120, Piso 3',
                'telefono_contacto' => '0221-429-5000 int 231',
                'email_institucional' => 'sistemas@salud.gob.ar',
                'activo' => true,
            ],
            [
                'ministerio_id' => $ms->id,
                'codigo' => 'TI-MS-02',
                'nombre' => 'Dirección de Informática Hospitalaria y Telesalud',
                'nivel_jerarquico' => 'DIRECCION',
                'es_homologada' => true,
                'resolucion_homologacion' => 'RES-2024-018-DPSIT',
                'domicilio_fisico' => 'Calle 51 N° 1120, Piso 4',
                'telefono_contacto' => '0221-429-5000 int 245',
                'email_institucional' => 'telesalud@salud.gob.ar',
                'activo' => true,
            ],
            [
                'ministerio_id' => $me->id,
                'codigo' => 'TI-ME-01',
                'nombre' => 'Dirección Provincial de Tecnología Educativa e Información',
                'nivel_jerarquico' => 'DIRECCION_PROVINCIAL',
                'es_homologada' => true,
                'resolucion_homologacion' => 'RES-2022-890-DPSIT',
                'domicilio_fisico' => 'Calle 13 e/ 56 y 57',
                'telefono_contacto' => '0221-429-8800',
                'email_institucional' => 'tecedu@educacion.gob.ar',
                'activo' => true,
            ],
            [
                'ministerio_id' => $meyf->id,
                'codigo' => 'TI-MEYF-01',
                'nombre' => 'Dirección General de Sistemas Hacendarios y Financieros',
                'nivel_jerarquico' => 'DIRECCION_GENERAL',
                'es_homologada' => true,
                'resolucion_homologacion' => 'RES-2021-305-DPSIT',
                'domicilio_fisico' => 'Calle 8 e/ 45 y 46',
                'telefono_contacto' => '0221-429-4400',
                'email_institucional' => 'sistemas@hacienda.gob.ar',
                'activo' => true,
            ],
            [
                'ministerio_id' => $mys->id,
                'codigo' => 'TI-MYS-01',
                'nombre' => 'Superintendencia de Comunicaciones e Informática Policial',
                'nivel_jerarquico' => 'DIRECCION_GENERAL',
                'es_homologada' => true,
                'resolucion_homologacion' => 'RES-2023-550-DPSIT',
                'domicilio_fisico' => 'Calle 2 e/ 51 y 53',
                'telefono_contacto' => '0221-429-3000',
                'email_institucional' => 'comunicaciones@seguridad.gob.ar',
                'activo' => true,
            ],
            [
                'ministerio_id' => $misp->id,
                'codigo' => 'TI-MISP-01',
                'nombre' => 'Dirección de Sistemas de Obras y Servicios Públicos',
                'nivel_jerarquico' => 'DIRECCION',
                'es_homologada' => false, // Área en proceso de homologación
                'resolucion_homologacion' => null,
                'domicilio_fisico' => 'Calle 7 N° 1267',
                'telefono_contacto' => '0221-429-5200',
                'email_institucional' => 'sistemas@infraestructura.gob.ar',
                'activo' => true,
            ],
        ];

        foreach ($areas as $a) {
            AreaTi::updateOrCreate(['codigo' => $a['codigo']], $a);
        }
    }
}
