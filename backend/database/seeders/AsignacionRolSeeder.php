<?php

namespace Database\Seeders;

use App\Models\AreaTi;
use App\Models\AsignacionRol;
use App\Models\Persona;
use App\Models\TipoRol;
use Illuminate\Database\Seeder;

class AsignacionRolSeeder extends Seeder
{
    public function run(): void
    {
        $pGonzalez = Persona::where('email_principal', 'mgonzalez@salud.gob.ar')->first();
        $pFernandez = Persona::where('email_principal', 'lfernandez@seguridad.salud.gob.ar')->first();
        $pRodriguez = Persona::where('email_principal', 'crodriguez@educacion.gob.ar')->first();
        $pBenitez = Persona::where('email_principal', 'mbenitez@educacion.gob.ar')->first();
        $pAlvarez = Persona::where('email_principal', 'ealvarez@hacienda.gob.ar')->first();
        $pValenzuela = Persona::where('email_principal', 'gvalenzuela@seguridad.gob.ar')->first();
        $pMorales = Persona::where('email_principal', 'mmorales@seguridad.gob.ar')->first();

        $aSalud01 = AreaTi::where('codigo', 'TI-MS-01')->first();
        $aSalud02 = AreaTi::where('codigo', 'TI-MS-02')->first();
        $aEdu01 = AreaTi::where('codigo', 'TI-ME-01')->first();
        $aHacienda01 = AreaTi::where('codigo', 'TI-MEYF-01')->first();
        $aSeg01 = AreaTi::where('codigo', 'TI-MYS-01')->first();

        $rDirProv = TipoRol::where('codigo', 'DIR_PROVINCIAL')->first();
        $rDirGral = TipoRol::where('codigo', 'DIR_GENERAL')->first();
        $rDirTec = TipoRol::where('codigo', 'DIR_TECNOLOGIA')->first();
        $rCiso = TipoRol::where('codigo', 'REF_CISO')->first();
        $rInterop = TipoRol::where('codigo', 'REF_INTEROP')->first();
        $rAdminCloud = TipoRol::where('codigo', 'ADMIN_CLOUD')->first();

        $asignaciones = [
            // 1. Histórico: Martín González fue Director en Educación (2021-2023)
            [
                'persona_id' => $pGonzalez->id,
                'area_ti_id' => $aEdu01->id,
                'tipo_rol_id' => $rDirTec->id,
                'fecha_inicio' => '2021-03-01',
                'fecha_fin' => '2023-12-10',
                'es_maxima_autoridad' => false,
                'acto_administrativo' => 'RES-2021-412-DGCYE',
                'observaciones' => 'Gestión finalizada por cambio de organismo.',
                'activo' => true,
            ],
            // 2. Vigente: Martín González es Máxima Autoridad (Director General) en Salud (2023-presente)
            [
                'persona_id' => $pGonzalez->id,
                'area_ti_id' => $aSalud01->id,
                'tipo_rol_id' => $rDirGral->id,
                'fecha_inicio' => '2023-12-11',
                'fecha_fin' => null, // Vigente
                'es_maxima_autoridad' => true,
                'acto_administrativo' => 'DEC-2023-2890-GBA',
                'observaciones' => 'Designación como Director General de Tecnologías en Salud.',
                'activo' => true,
            ],
            // 3. Vigente: Laura Fernández es CISO en Salud
            [
                'persona_id' => $pFernandez->id,
                'area_ti_id' => $aSalud01->id,
                'tipo_rol_id' => $rCiso->id,
                'fecha_inicio' => '2024-01-15',
                'fecha_fin' => null,
                'es_maxima_autoridad' => false,
                'acto_administrativo' => 'RES-2024-045-MS',
                'observaciones' => 'Referente sectorial ante CSIRT.',
                'activo' => true,
            ],
            // 4. Vigente: Carlos Rodríguez es Máxima Autoridad en Educación
            [
                'persona_id' => $pRodriguez->id,
                'area_ti_id' => $aEdu01->id,
                'tipo_rol_id' => $rDirProv->id,
                'fecha_inicio' => '2022-02-01',
                'fecha_fin' => null,
                'es_maxima_autoridad' => true,
                'acto_administrativo' => 'DEC-2022-1100-GBA',
                'observaciones' => 'Máxima autoridad de tecnología educativa.',
                'activo' => true,
            ],
            // 5. Vigente: María Florencia Benítez es Referente de Interoperabilidad en Educación
            [
                'persona_id' => $pBenitez->id,
                'area_ti_id' => $aEdu01->id,
                'tipo_rol_id' => $rInterop->id,
                'fecha_inicio' => '2022-06-01',
                'fecha_fin' => null,
                'es_maxima_autoridad' => false,
                'acto_administrativo' => 'DISPO-2022-88-DPTEI',
                'observaciones' => 'Referente técnica de intercambio de datos con Renaper y SISA.',
                'activo' => true,
            ],
            // 6. Vigente: Esteban Alvarez es Máxima Autoridad en Economía y Finanzas
            [
                'persona_id' => $pAlvarez->id,
                'area_ti_id' => $aHacienda01->id,
                'tipo_rol_id' => $rDirGral->id,
                'fecha_inicio' => '2020-01-01',
                'fecha_fin' => null,
                'es_maxima_autoridad' => true,
                'acto_administrativo' => 'DEC-2020-0089-GBA',
                'observaciones' => 'Director General de Sistemas Hacendarios.',
                'activo' => true,
            ],
            // 7. Vigente: Guillermo Valenzuela es Máxima Autoridad en Seguridad
            [
                'persona_id' => $pValenzuela->id,
                'area_ti_id' => $aSeg01->id,
                'tipo_rol_id' => $rDirGral->id,
                'fecha_inicio' => '2023-01-10',
                'fecha_fin' => null,
                'es_maxima_autoridad' => true,
                'acto_administrativo' => 'DEC-2023-0504-GBA',
                'observaciones' => 'Superintendente de Comunicaciones.',
                'activo' => true,
            ],
            // 8. Vigente: Mariana Morales es CISO en Seguridad
            [
                'persona_id' => $pMorales->id,
                'area_ti_id' => $aSeg01->id,
                'tipo_rol_id' => $rCiso->id,
                'fecha_inicio' => '2023-03-01',
                'fecha_fin' => null,
                'es_maxima_autoridad' => false,
                'acto_administrativo' => 'RES-2023-112-MYS',
                'observaciones' => 'Referente de Seguridad de la Información.',
                'activo' => true,
            ],
        ];

        foreach ($asignaciones as $asig) {
            AsignacionRol::create($asig);
        }
    }
}
