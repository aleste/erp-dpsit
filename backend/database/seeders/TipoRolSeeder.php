<?php

namespace Database\Seeders;

use App\Models\TipoRol;
use Illuminate\Database\Seeder;

class TipoRolSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'codigo' => 'DIR_PROVINCIAL',
                'nombre' => 'Director Provincial de TI',
                'descripcion' => 'Máxima autoridad política y técnica del área de tecnologías de la información a nivel provincial.',
                'nivel_responsabilidad' => 'EJECUTIVO',
                'es_maxima_autoridad_default' => true,
                'activo' => true,
            ],
            [
                'codigo' => 'DIR_GENERAL',
                'nombre' => 'Director General de Informática',
                'descripcion' => 'Responsable superior de la gestión informática ministerial u organismo central.',
                'nivel_responsabilidad' => 'EJECUTIVO',
                'es_maxima_autoridad_default' => true,
                'activo' => true,
            ],
            [
                'codigo' => 'DIR_TECNOLOGIA',
                'nombre' => 'Director de Tecnología',
                'descripcion' => 'Dirección ejecutiva de operaciones e infraestructura tecnológica.',
                'nivel_responsabilidad' => 'EJECUTIVO',
                'es_maxima_autoridad_default' => true,
                'activo' => true,
            ],
            [
                'codigo' => 'REF_CISO',
                'nombre' => 'Referente de Seguridad de la Información (CISO)',
                'descripcion' => 'Referente sectorial ante el CSIRT Provincial y responsable de políticas de ciberseguridad.',
                'nivel_responsabilidad' => 'TACTICO',
                'es_maxima_autoridad_default' => false,
                'activo' => true,
            ],
            [
                'codigo' => 'REF_INTEROP',
                'nombre' => 'Referente de Interoperabilidad e Integración',
                'descripcion' => 'Responsable de APIs, servicios web y consumo de datos mediante la plataforma provincial.',
                'nivel_responsabilidad' => 'TACTICO',
                'es_maxima_autoridad_default' => false,
                'activo' => true,
            ],
            [
                'codigo' => 'ADMIN_CLOUD',
                'nombre' => 'Administrador de Infraestructura Cloud',
                'descripcion' => 'Gestor de proyectos de nube provincial, cómputo y almacenamiento virtual.',
                'nivel_responsabilidad' => 'OPERATIVO',
                'es_maxima_autoridad_default' => false,
                'activo' => true,
            ],
            [
                'codigo' => 'DEV_LEAD',
                'nombre' => 'Líder Técnico de Desarrollo de Software',
                'descripcion' => 'Responsable de arquitectura de software y gobernanza de código en el organismo.',
                'nivel_responsabilidad' => 'TACTICO',
                'es_maxima_autoridad_default' => false,
                'activo' => true,
            ],
            [
                'codigo' => 'RESP_ADMIN',
                'nombre' => 'Responsable Administrativo de TI',
                'descripcion' => 'Gestión de compras tecnológicas, licencias, contrataciones y actas de acuerdo.',
                'nivel_responsabilidad' => 'OPERATIVO',
                'es_maxima_autoridad_default' => false,
                'activo' => true,
            ],
        ];

        foreach ($roles as $r) {
            TipoRol::updateOrCreate(['codigo' => $r['codigo']], $r);
        }
    }
}
