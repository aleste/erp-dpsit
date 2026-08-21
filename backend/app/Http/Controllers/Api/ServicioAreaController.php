<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServicioArea;
use App\Models\ServicioReferente;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServicioAreaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ServicioArea::with([
            'areaTi.ministerio',
            'catalogoServicio',
            'referentes.persona',
            'documentos',
        ]);

        if ($request->filled('area_ti_id')) {
            $query->where('area_ti_id', $request->area_ti_id);
        }

        if ($request->filled('catalogo_servicio_id')) {
            $query->where('catalogo_servicio_id', $request->catalogo_servicio_id);
        }

        if ($request->filled('estado_servicio')) {
            $query->where('estado_servicio', $request->estado_servicio);
        }

        if ($request->filled('ministerio_id')) {
            $ministerioId = $request->ministerio_id;
            $query->whereHas('areaTi', function ($q) use ($ministerioId) {
                $q->where('ministerio_id', $ministerioId);
            });
        }

        $servicios = $query->orderBy('codigo_instancia')->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $servicios,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $servicio = ServicioArea::with([
            'areaTi.ministerio',
            'catalogoServicio',
            'referentes.persona',
            'documentos.subidoPor',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $servicio,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'area_ti_id' => 'required|exists:areas_ti,id',
            'catalogo_servicio_id' => 'required|exists:catalogo_servicios,id',
            'codigo_instancia' => 'required|string|max:100|unique:servicios_areas,codigo_instancia',
            'estado_servicio' => 'required|in:SOLICITADO,EN_PROCESO,ACTIVO,SUSPENDIDO,DADO_DE_BAJA',
            'sla_nivel' => 'nullable|string|max:100',
            'fecha_alta' => 'required|date',
            'fecha_baja' => 'nullable|date|after_or_equal:fecha_alta',
            'observaciones' => 'nullable|string',
            'referentes' => 'nullable|array',
            'referentes.*.persona_id' => 'required|exists:personas,id',
            'referentes.*.tipo_referente' => 'required|in:TECNICO_PRINCIPAL,TECNICO_ALTERNO,ADMINISTRATIVO,SEGURIDAD',
            'referentes.*.es_principal' => 'boolean',
        ]);

        $servicio = DB::transaction(function () use ($validated) {
            $referentesData = $validated['referentes'] ?? [];
            unset($validated['referentes']);

            $instancia = ServicioArea::create($validated);

            foreach ($referentesData as $ref) {
                $instancia->referentes()->create($ref);
            }

            return $instancia;
        });

        AuditService::log('CREATE', 'SERVICIOS_AREAS', $servicio->id, null, $servicio->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Servicio aprovisionado y registrado con éxito',
            'data' => $servicio->load(['areaTi.ministerio', 'catalogoServicio', 'referentes.persona']),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $servicio = ServicioArea::findOrFail($id);
        $anterior = $servicio->toArray();

        $validated = $request->validate([
            'area_ti_id' => 'required|exists:areas_ti,id',
            'catalogo_servicio_id' => 'required|exists:catalogo_servicios,id',
            'codigo_instancia' => "required|string|max:100|unique:servicios_areas,codigo_instancia,{$id}",
            'estado_servicio' => 'required|in:SOLICITADO,EN_PROCESO,ACTIVO,SUSPENDIDO,DADO_DE_BAJA',
            'sla_nivel' => 'nullable|string|max:100',
            'fecha_alta' => 'required|date',
            'fecha_baja' => 'nullable|date|after_or_equal:fecha_alta',
            'observaciones' => 'nullable|string',
        ]);

        $servicio->update($validated);
        AuditService::log('UPDATE', 'SERVICIOS_AREAS', $servicio->id, $anterior, $servicio->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Parámetros del servicio actualizados exitosamente',
            'data' => $servicio->load(['areaTi.ministerio', 'catalogoServicio', 'referentes.persona']),
        ]);
    }

    /**
     * Asignar o actualizar referente a la instancia del servicio
     */
    public function syncReferente(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'persona_id' => 'required|exists:personas,id',
            'tipo_referente' => 'required|in:TECNICO_PRINCIPAL,TECNICO_ALTERNO,ADMINISTRATIVO,SEGURIDAD',
            'es_principal' => 'boolean',
        ]);

        $servicio = ServicioArea::findOrFail($id);

        $referente = ServicioReferente::updateOrCreate(
            [
                'servicio_area_id' => $servicio->id,
                'persona_id' => $validated['persona_id'],
                'tipo_referente' => $validated['tipo_referente'],
            ],
            [
                'es_principal' => $validated['es_principal'] ?? false,
                'notificaciones_activas' => true,
            ]
        );

        AuditService::log('UPDATE', 'SERVICIOS_AREAS', $servicio->id, null, null, "Referente asignado: {$validated['tipo_referente']}");

        return response()->json([
            'success' => true,
            'message' => 'Referente asignado al servicio con éxito',
            'data' => $referente->load('persona'),
        ]);
    }

    /**
     * Quitar o desvincular un referente del servicio tecnológico
     */
    public function removeReferente(int $id, int $referenteId): JsonResponse
    {
        $servicio = ServicioArea::findOrFail($id);
        $referente = ServicioReferente::where('servicio_area_id', $servicio->id)->where('id', $referenteId)->firstOrFail();
        
        $personaNombre = $referente->persona 
            ? "{$referente->persona->apellido}, {$referente->persona->nombre}" 
            : "ID {$referente->persona_id}";
            
        $referente->delete();

        AuditService::log('DELETE', 'SERVICIOS_AREAS', $servicio->id, null, null, "Referente desvinculado del servicio: {$personaNombre}");

        return response()->json([
            'success' => true,
            'message' => "Referente {$personaNombre} desvinculado del servicio correctamente",
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $servicio = ServicioArea::findOrFail($id);
        $anterior = $servicio->toArray();
        $servicio->delete();
        AuditService::log('DELETE', 'SERVICIOS_AREAS', $id, $anterior, null);

        return response()->json([
            'success' => true,
            'message' => 'Instancia de servicio eliminada con éxito',
        ]);
    }
}
