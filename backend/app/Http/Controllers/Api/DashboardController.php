<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AreaTi;
use App\Models\AsignacionRol;
use App\Models\CatalogoServicio;
use App\Models\Ministerio;
use App\Models\Persona;
use App\Models\ServicioArea;
use App\Models\UserModulo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Indicadores del panel de inicio. Cada bloque se calcula solo si el usuario tiene el módulo (D-34);
     * los ausentes no se envían.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $puede = fn (string $modulo) => $user->puedeEnModulo($modulo, 'CONSULTA');

        $totales = [];
        $data = [];

        if ($puede(UserModulo::ORGANISMOS)) {
            $totales['ministerios'] = Ministerio::where('activo', true)->count();
            $totales['areas_ti'] = AreaTi::where('activo', true)->count();
            $totales['areas_homologadas'] = AreaTi::where('activo', true)->where('es_homologada', true)->count();
            $totales['asignaciones_vigentes'] = AsignacionRol::vigente()->count();
            $totales['maximas_autoridades'] = AsignacionRol::vigente()->maximaAutoridad()->count();

            $data['ultimas_asignaciones'] = AsignacionRol::with(['persona', 'areaTi.ministerio', 'tipoRol'])
                ->orderByDesc('created_at')
                ->take(5)
                ->get();
        }

        if ($puede(UserModulo::PADRON)) {
            $totales['personas'] = Persona::where('activo', true)->count();
        }

        if ($puede(UserModulo::SERVICIOS)) {
            $totales['servicios_activos'] = ServicioArea::where('estado_servicio', 'ACTIVO')->count();

            // Distribución de servicios por catálogo
            $data['servicios_por_tipo'] = CatalogoServicio::withCount(['serviciosAreas' => function ($q) {
                $q->where('estado_servicio', 'ACTIVO');
            }])->get(['id', 'codigo', 'nombre', 'categoria', 'servicios_areas_count']);
        }

        return response()->json([
            'success' => true,
            'data' => ['totales' => (object) $totales] + $data,
        ]);
    }
}
