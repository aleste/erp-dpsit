<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AreaTi;
use App\Models\AsignacionRol;
use App\Models\CatalogoServicio;
use App\Models\Ministerio;
use App\Models\Persona;
use App\Models\ServicioArea;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $totalMinisterios = Ministerio::where('activo', true)->count();
        $totalAreasTi = AreaTi::where('activo', true)->count();
        $totalAreasHomologadas = AreaTi::where('activo', true)->where('es_homologada', true)->count();
        $totalPersonas = Persona::where('activo', true)->count();
        
        $asignacionesVigentes = AsignacionRol::vigente()->count();
        $maximasAutoridadesAsignadas = AsignacionRol::vigente()->maximaAutoridad()->count();
        
        $serviciosActivos = ServicioArea::where('estado_servicio', 'ACTIVO')->count();

        // Distribución de servicios por catálogo
        $serviciosPorTipo = CatalogoServicio::withCount(['serviciosAreas' => function ($q) {
            $q->where('estado_servicio', 'ACTIVO');
        }])->get(['id', 'codigo', 'nombre', 'categoria', 'servicios_areas_count']);

        // Áreas TI con sus autoridades
        $ultimasAsignaciones = AsignacionRol::with(['persona', 'areaTi.ministerio', 'tipoRol'])
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'totales' => [
                    'ministerios' => $totalMinisterios,
                    'areas_ti' => $totalAreasTi,
                    'areas_homologadas' => $totalAreasHomologadas,
                    'personas' => $totalPersonas,
                    'asignaciones_vigentes' => $asignacionesVigentes,
                    'maximas_autoridades' => $maximasAutoridadesAsignadas,
                    'servicios_activos' => $serviciosActivos,
                ],
                'servicios_por_tipo' => $serviciosPorTipo,
                'ultimas_asignaciones' => $ultimasAsignaciones,
            ],
        ]);
    }
}
