<?php

namespace Database\Seeders;

use App\Models\AreaTi;
use App\Models\CatalogoServicio;
use App\Models\Persona;
use App\Models\ServicioArea;
use App\Models\ServicioReferente;
use Illuminate\Database\Seeder;

class ServicioAreaSeeder extends Seeder
{
    public function run(): void
    {
        $aSalud01 = AreaTi::where('codigo', 'TI-MS-01')->first();
        $aEdu01 = AreaTi::where('codigo', 'TI-ME-01')->first();
        $aHacienda01 = AreaTi::where('codigo', 'TI-MEYF-01')->first();

        $srvCloud = CatalogoServicio::where('codigo', 'SRV_NUBE_PROV')->first();
        $srvSso = CatalogoServicio::where('codigo', 'SRV_SSO_PROV')->first();
        $srvInterop = CatalogoServicio::where('codigo', 'SRV_INTEROP_BUS')->first();

        $pGonzalez = Persona::where('email_principal', 'mgonzalez@salud.gob.ar')->first();
        $pFernandez = Persona::where('email_principal', 'lfernandez@seguridad.salud.gob.ar')->first();
        $pBenitez = Persona::where('email_principal', 'mbenitez@educacion.gob.ar')->first();
        $pRodriguez = Persona::where('email_principal', 'crodriguez@educacion.gob.ar')->first();

        // 1. Instancia Nube para Ministerio de Salud
        $instanciaSaludCloud = ServicioArea::create([
            'area_ti_id' => $aSalud01->id,
            'catalogo_servicio_id' => $srvCloud->id,
            'codigo_instancia' => 'SRV-CLOUD-MS-PROD',
            'estado_servicio' => 'ACTIVO',
            'sla_nivel' => 'CRITICO',
            'fecha_alta' => '2024-01-10',
            'observaciones' => 'Cluster productivo para Historia Clínica Digital y Gestión de Camas.',
        ]);

        ServicioReferente::create([
            'servicio_area_id' => $instanciaSaludCloud->id,
            'persona_id' => $pGonzalez->id,
            'tipo_referente' => 'TECNICO_PRINCIPAL',
            'es_principal' => true,
        ]);

        ServicioReferente::create([
            'servicio_area_id' => $instanciaSaludCloud->id,
            'persona_id' => $pFernandez->id,
            'tipo_referente' => 'SEGURIDAD',
            'es_principal' => false,
        ]);

        // 2. Instancia SSO para Salud
        $instanciaSaludSso = ServicioArea::create([
            'area_ti_id' => $aSalud01->id,
            'catalogo_servicio_id' => $srvSso->id,
            'codigo_instancia' => 'SRV-SSO-MS-PORTAL',
            'estado_servicio' => 'ACTIVO',
            'sla_nivel' => 'ALTO',
            'fecha_alta' => '2024-02-01',
            'observaciones' => 'Autenticación con validación de matrícula del Colegio de Médicos.',
        ]);

        ServicioReferente::create([
            'servicio_area_id' => $instanciaSaludSso->id,
            'persona_id' => $pGonzalez->id,
            'tipo_referente' => 'TECNICO_PRINCIPAL',
            'es_principal' => true,
        ]);

        // 3. Instancia Interoperabilidad para Educación
        $instanciaEduInterop = ServicioArea::create([
            'area_ti_id' => $aEdu01->id,
            'catalogo_servicio_id' => $srvInterop->id,
            'codigo_instancia' => 'SRV-BUS-ME-MIS-ALUMNOS',
            'estado_servicio' => 'ACTIVO',
            'sla_nivel' => 'ALTO',
            'fecha_alta' => '2023-04-15',
            'observaciones' => 'Validación cruzada de identidad para matrícula escolar.',
        ]);

        ServicioReferente::create([
            'servicio_area_id' => $instanciaEduInterop->id,
            'persona_id' => $pBenitez->id,
            'tipo_referente' => 'TECNICO_PRINCIPAL',
            'es_principal' => true,
        ]);

        ServicioReferente::create([
            'servicio_area_id' => $instanciaEduInterop->id,
            'persona_id' => $pRodriguez->id,
            'tipo_referente' => 'ADMINISTRATIVO',
            'es_principal' => false,
        ]);
    }
}
