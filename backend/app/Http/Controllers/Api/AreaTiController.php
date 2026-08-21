<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AreaTi;
use App\Models\AsignacionRol;
use App\Models\TipoRol;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AreaTiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AreaTi::with([
            'ministerio',
            'maximaAutoridadVigente.persona',
            'maximaAutoridadVigente.tipoRol',
        ])->withCount(['asignacionesVigentes', 'servicios', 'documentos']);

        if ($request->filled('ministerio_id')) {
            $query->where('ministerio_id', $request->ministerio_id);
        }

        if ($request->filled('es_homologada')) {
            $query->where('es_homologada', filter_var($request->es_homologada, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('nivel_jerarquico')) {
            $query->where('nivel_jerarquico', $request->nivel_jerarquico);
        }

        if ($request->filled('q')) {
            $term = trim($request->q);
            $query->where(function ($q) use ($term) {
                $q->where('nombre', 'ILIKE', "%{$term}%")
                  ->orWhere('codigo', 'ILIKE', "%{$term}%")
                  ->orWhere('resolucion_homologacion', 'ILIKE', "%{$term}%")
                  ->orWhere('email_institucional', 'ILIKE', "%{$term}%");
            });
        }

        $areas = $query->orderBy('nombre')->get();

        // Resumen estadístico
        $totalAreas = AreaTi::count();
        $totalHomologadas = AreaTi::where('es_homologada', true)->count();
        $totalEnTramite = AreaTi::where('es_homologada', false)->count();

        return response()->json([
            'success' => true,
            'data' => $areas,
            'stats' => [
                'total' => $totalAreas,
                'homologadas' => $totalHomologadas,
                'en_tramite' => $totalEnTramite,
            ]
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $area = AreaTi::with([
            'ministerio',
            'maximaAutoridadVigente.persona',
            'maximaAutoridadVigente.tipoRol',
            'asignaciones' => function ($q) {
                $q->with(['persona', 'tipoRol'])->orderByDesc('fecha_inicio');
            },
            'servicios.catalogoServicio',
            'servicios.referentes.persona',
            'documentos.subidoPor',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $area,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ministerio_id' => 'required|exists:ministerios,id',
            'codigo' => 'required|string|max:50|unique:areas_ti,codigo',
            'nombre' => 'required|string|max:255',
            'nivel_jerarquico' => 'required|string|max:50',
            'es_homologada' => 'boolean',
            'resolucion_homologacion' => 'nullable|string|max:100',
            'domicilio_fisico' => 'nullable|string|max:255',
            'telefono_contacto' => 'nullable|string|max:50',
            'email_institucional' => 'nullable|email|max:255',
            'activo' => 'boolean',
        ]);

        $area = AreaTi::create($validated);
        AuditService::log('CREATE', 'AREAS_TI', $area->id, null, $area->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Área TI homologada registrada con éxito',
            'data' => $area->load('ministerio'),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $area = AreaTi::findOrFail($id);
        $anterior = $area->toArray();

        $validated = $request->validate([
            'ministerio_id' => 'required|exists:ministerios,id',
            'codigo' => "required|string|max:50|unique:areas_ti,codigo,{$id}",
            'nombre' => 'required|string|max:255',
            'nivel_jerarquico' => 'required|string|max:50',
            'es_homologada' => 'boolean',
            'resolucion_homologacion' => 'nullable|string|max:100',
            'domicilio_fisico' => 'nullable|string|max:255',
            'telefono_contacto' => 'nullable|string|max:50',
            'email_institucional' => 'nullable|email|max:255',
            'activo' => 'boolean',
        ]);

        $area->update($validated);
        AuditService::log('UPDATE', 'AREAS_TI', $area->id, $anterior, $area->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Área TI actualizada con éxito',
            'data' => $area->load('ministerio'),
        ]);
    }

    /**
     * Designar o reasignar la Máxima Autoridad del Área TI
     */
    public function designarAutoridad(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'persona_id' => 'required|exists:personas,id',
            'tipo_rol_id' => 'required|exists:tipos_rol,id',
            'fecha_inicio' => 'required|date',
            'acto_administrativo' => 'nullable|string|max:150',
            'observaciones' => 'nullable|string',
        ]);

        $area = AreaTi::findOrFail($id);

        $asignacion = DB::transaction(function () use ($area, $validated) {
            // Finalizar vigencia de cualquier máxima autoridad previa en esta área
            AsignacionRol::where('area_ti_id', $area->id)
                ->where('es_maxima_autoridad', true)
                ->whereNull('fecha_fin')
                ->update([
                    'fecha_fin' => now()->toDateString(),
                    'observaciones' => DB::raw("CONCAT(COALESCE(observaciones, ''), '\nCese por nueva designación de autoridad')")
                ]);

            // Crear la nueva asignación de máxima autoridad
            return AsignacionRol::create([
                'persona_id' => $validated['persona_id'],
                'area_ti_id' => $area->id,
                'tipo_rol_id' => $validated['tipo_rol_id'],
                'fecha_inicio' => $validated['fecha_inicio'],
                'fecha_fin' => null,
                'es_maxima_autoridad' => true,
                'acto_administrativo' => $validated['acto_administrativo'] ?? null,
                'observaciones' => $validated['observaciones'] ?? 'Designación de Máxima Autoridad del Área',
                'activo' => true,
            ]);
        });

        AuditService::log('UPDATE', 'AREAS_TI', $area->id, null, null, "Nueva máxima autoridad designada");

        return response()->json([
            'success' => true,
            'message' => 'Máxima autoridad designada exitosamente',
            'data' => $asignacion->load(['persona', 'tipoRol']),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $area = AreaTi::findOrFail($id);

        if ($area->servicios()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el área porque posee servicios tecnológicos asignados.',
            ], 422);
        }

        $anterior = $area->toArray();
        $area->delete();
        AuditService::log('DELETE', 'AREAS_TI', $id, $anterior, null);

        return response()->json([
            'success' => true,
            'message' => 'Área TI eliminada con éxito',
        ]);
    }
}
