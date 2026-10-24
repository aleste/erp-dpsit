<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AutoridadOrganismo;
use App\Models\Ministerio;
use App\Services\AuditService;
use App\Services\AutoridadOrganismoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutoridadOrganismoController extends Controller
{
    public function designar(Request $request, int $ministerioId, AutoridadOrganismoService $service): JsonResponse
    {
        $validated = $request->validate([
            'persona_id' => 'required|integer|exists:personas,id,deleted_at,NULL',
            'cargo' => 'nullable|string|max:150',
            'fecha_inicio' => 'nullable|date',
            'acto_administrativo' => 'nullable|string|max:150',
            'observaciones' => 'nullable|string',
        ]);

        $ministerio = Ministerio::findOrFail($ministerioId);
        $autoridad = $service->designar($ministerio, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Máxima autoridad designada exitosamente',
            'data' => $autoridad->load('persona'),
        ], 201);
    }

    /**
     * Corrección de datos de una designación (cargo, acto, fechas). El reemplazo de la persona
     * se hace con una designación nueva para conservar el historial.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $autoridad = AutoridadOrganismo::findOrFail($id);
        $anterior = $autoridad->toArray();

        $validated = $request->validate([
            'cargo' => 'nullable|string|max:150',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'acto_administrativo' => 'nullable|string|max:150',
            'observaciones' => 'nullable|string',
        ]);

        $autoridad->update($validated);
        AuditService::log('UPDATE', 'AUTORIDADES_ORGANISMO', $autoridad->id, $anterior, $autoridad->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Datos de la autoridad actualizados',
            'data' => $autoridad->load('persona'),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $autoridad = AutoridadOrganismo::findOrFail($id);
        $anterior = $autoridad->toArray();
        $autoridad->delete();
        AuditService::log('DELETE', 'AUTORIDADES_ORGANISMO', $id, $anterior, null);

        return response()->json([
            'success' => true,
            'message' => 'Designación eliminada',
        ]);
    }
}
