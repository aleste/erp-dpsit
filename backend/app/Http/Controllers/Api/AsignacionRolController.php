<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AsignacionRol;
use App\Models\TipoRol;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AsignacionRolController extends Controller
{
    /**
     * Búsqueda por rol y listado general con filtros cruzados
     */
    public function index(Request $request): JsonResponse
    {
        $query = AsignacionRol::with([
            'persona',
            'areaTi.ministerio',
            'tipoRol',
            'documentos',
        ]);

        // Filtro por Rol específico (Búsqueda por rol requerida)
        if ($request->filled('tipo_rol_id')) {
            $query->where('tipo_rol_id', $request->tipo_rol_id);
        }

        // Filtro por Ministerio
        if ($request->filled('ministerio_id')) {
            $query->porMinisterio($request->ministerio_id);
        }

        // Filtro por Área TI
        if ($request->filled('area_ti_id')) {
            $query->where('area_ti_id', $request->area_ti_id);
        }

        // Filtro por Persona
        if ($request->filled('persona_id')) {
            $query->where('persona_id', $request->persona_id);
        }

        // Filtro por estado de vigencia
        if ($request->filled('estado')) {
            if ($request->estado === 'VIGENTE') {
                $query->vigente();
            } elseif ($request->estado === 'HISTORICO') {
                $query->historico();
            }
        }

        // Filtro solo máximas autoridades
        if ($request->boolean('solo_maximas_autoridades')) {
            $query->maximaAutoridad();
        }

        // Búsqueda rápida por nombre de persona o área
        if ($request->filled('q')) {
            $term = trim($request->q);
            $query->whereHas('persona', function ($q) use ($term) {
                $q->where('apellido', 'ILIKE', "%{$term}%")
                  ->orWhere('nombre', 'ILIKE', "%{$term}%")
                  ->orWhere('cuil_cuit', 'LIKE', "%{$term}%");
            });
        }

        $asignaciones = $query->orderByDesc('fecha_inicio')->paginate($request->get('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $asignaciones,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $asignacion = AsignacionRol::with([
            'persona',
            'areaTi.ministerio',
            'tipoRol',
            'documentos',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $asignacion,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'persona_id' => 'required|exists:personas,id',
            'area_ti_id' => 'required|exists:areas_ti,id',
            'tipo_rol_id' => 'required|exists:tipos_rol,id',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'es_maxima_autoridad' => 'boolean',
            'acto_administrativo' => 'nullable|string|max:150',
            'observaciones' => 'nullable|string',
            'activo' => 'boolean',
        ]);

        // Si se marca como máxima autoridad y no se especificó explícitamente, o si el rol lo tiene por defecto
        if (!isset($validated['es_maxima_autoridad'])) {
            $tipoRol = TipoRol::find($validated['tipo_rol_id']);
            $validated['es_maxima_autoridad'] = $tipoRol?->es_maxima_autoridad_default ?? false;
        }

        $asignacion = AsignacionRol::create($validated);
        AuditService::log('CREATE', 'ASIGNACIONES_ROL', $asignacion->id, null, $asignacion->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Designación y asignación de rol registrada exitosamente',
            'data' => $asignacion->load(['persona', 'areaTi.ministerio', 'tipoRol']),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $asignacion = AsignacionRol::findOrFail($id);
        $anterior = $asignacion->toArray();

        $validated = $request->validate([
            'persona_id' => 'required|exists:personas,id',
            'area_ti_id' => 'required|exists:areas_ti,id',
            'tipo_rol_id' => 'required|exists:tipos_rol,id',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'es_maxima_autoridad' => 'boolean',
            'acto_administrativo' => 'nullable|string|max:150',
            'observaciones' => 'nullable|string',
            'activo' => 'boolean',
        ]);

        $asignacion->update($validated);
        AuditService::log('UPDATE', 'ASIGNACIONES_ROL', $asignacion->id, $anterior, $asignacion->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Asignación de rol actualizada con éxito',
            'data' => $asignacion->load(['persona', 'areaTi.ministerio', 'tipoRol']),
        ]);
    }

    /**
     * Finalizar vigencia de una asignación (dar de baja o registrar pase)
     */
    public function finalizarVigencia(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'fecha_fin' => 'required|date',
            'motivo' => 'nullable|string',
        ]);

        $asignacion = AsignacionRol::findOrFail($id);
        $anterior = $asignacion->toArray();

        $asignacion->update([
            'fecha_fin' => $request->fecha_fin,
            'observaciones' => trim(($asignacion->observaciones ?? '') . "\nBaja de cargo: " . $request->motivo),
        ]);

        AuditService::log('UPDATE', 'ASIGNACIONES_ROL', $asignacion->id, $anterior, $asignacion->toArray(), 'Finalización formal de vigencia');

        return response()->json([
            'success' => true,
            'message' => 'Vigencia de asignación finalizada correctamente',
            'data' => $asignacion,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $asignacion = AsignacionRol::findOrFail($id);
        $anterior = $asignacion->toArray();
        $asignacion->delete();
        AuditService::log('DELETE', 'ASIGNACIONES_ROL', $id, $anterior, null);

        return response()->json([
            'success' => true,
            'message' => 'Asignación eliminada con éxito',
        ]);
    }
}
