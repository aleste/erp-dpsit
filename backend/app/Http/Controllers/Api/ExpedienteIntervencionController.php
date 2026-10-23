<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpedienteIntervencion;
use App\Services\ExpedienteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Acciones sobre una intervención. Cada una registra un movimiento en el historial (ExpedienteService).
 */
class ExpedienteIntervencionController extends Controller
{
    public function __construct(
        protected ExpedienteService $expedienteService
    ) {}

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'caratula' => 'sometimes|nullable|string|max:2000',
            'ministerio_id' => 'sometimes|nullable|exists:ministerios,id',
            'tipo_requerimiento_id' => 'sometimes|nullable|exists:expediente_tipos_requerimiento,id',
            'descripcion' => 'sometimes|nullable|string|max:2000',
            'prioridad' => ['sometimes', Rule::in(ExpedienteIntervencion::PRIORIDADES)],
            'proxima_accion' => 'sometimes|nullable|string|max:255',
            'fecha_proxima_accion' => 'sometimes|nullable|date',
            'fecha_vencimiento' => 'sometimes|nullable|date',
            'etb' => 'sometimes|nullable|string|max:100',
            'con_acto_administrativo' => ['sometimes', 'nullable', Rule::in(ExpedienteIntervencion::ACTO_ADMINISTRATIVO)],
            'observaciones' => 'sometimes|nullable|string|max:2000',
            'documento_respuesta' => 'sometimes|nullable|string|max:150',
        ]);

        $intervencion = $this->expedienteService->actualizarDatos($this->intervencion($id), $validated, $request->user());

        return $this->respuesta($intervencion, 'Datos actualizados');
    }

    public function asignar(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'responsable_id' => 'nullable|exists:users,id',
            'colaboradores' => 'nullable|array',
            'colaboradores.*' => 'integer|exists:users,id',
            'descripcion' => 'nullable|string|max:500',
        ]);

        $intervencion = $this->expedienteService->asignar(
            $this->intervencion($id),
            $validated['responsable_id'] ?? null,
            $validated['colaboradores'] ?? [],
            $request->user(),
            $validated['descripcion'] ?? null
        );

        return $this->respuesta($intervencion, 'Asignación registrada');
    }

    public function cambiarEstado(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'estado_id' => 'required|exists:expediente_estados,id',
            'descripcion' => 'nullable|string|max:2000',
            'fecha' => 'nullable|date|before_or_equal:today',
        ]);

        $intervencion = $this->expedienteService->cambiarEstado(
            $this->intervencion($id), (int) $validated['estado_id'], $request->user(),
            $validated['descripcion'] ?? null, $validated['fecha'] ?? null
        );

        return $this->respuesta($intervencion, 'Estado actualizado');
    }

    public function enviarRevision(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'revisor_id' => 'required|exists:users,id',
            'fecha' => 'required|date',
            'estado_id' => 'nullable|exists:expediente_estados,id',
            'documento_respuesta' => 'nullable|string|max:150',
            'descripcion' => 'nullable|string|max:2000',
        ]);

        $intervencion = $this->expedienteService->enviarRevision($this->intervencion($id), $validated, $request->user());

        return $this->respuesta($intervencion, 'Envío a revisión registrado');
    }

    public function firma(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'fecha' => 'required|date',
            'documento_respuesta' => 'nullable|string|max:150',
            'descripcion' => 'nullable|string|max:2000',
        ]);

        $intervencion = $this->expedienteService->registrarFirma($this->intervencion($id), $validated, $request->user());

        return $this->respuesta($intervencion, 'Firma registrada');
    }

    public function egresar(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'fecha_egreso' => 'required|date',
            'estado_id' => 'required|exists:expediente_estados,id',
            'dependencia_destino_id' => 'nullable|exists:dependencias_destino,id',
            'dependencia_destino_nombre' => 'nullable|required_without:dependencia_destino_id|string|max:255',
            'documento_respuesta' => 'nullable|string|max:150',
            'descripcion' => 'nullable|string|max:2000',
        ]);

        $intervencion = $this->expedienteService->egresar($this->intervencion($id), $validated, $request->user());

        return $this->respuesta($intervencion, 'Egreso registrado');
    }

    public function nota(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'descripcion' => 'required|string|max:2000',
            'fecha' => 'nullable|date|before_or_equal:today',
            'referencia_documento' => 'nullable|string|max:150',
            'fecha_proximo_paso' => 'nullable|date',
        ]);

        $movimiento = $this->expedienteService->agregarNota($this->intervencion($id), $validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Nota registrada',
            'data' => $movimiento,
        ], 201);
    }

    public function reabrir(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'motivo' => 'required|string|max:1000',
            'estado_id' => 'required|exists:expediente_estados,id',
        ]);

        $intervencion = $this->expedienteService->reabrir($this->intervencion($id), $validated, $request->user());

        return $this->respuesta($intervencion, 'Intervención reabierta');
    }

    public function corregirFechas(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'motivo' => 'required|string|max:1000',
            'fecha_ingreso' => 'sometimes|required|date|before_or_equal:today',
            'fecha_egreso' => 'sometimes|nullable|date|before_or_equal:today',
            'fecha_envio_revision' => 'sometimes|nullable|date|before_or_equal:today',
            'fecha_firma' => 'sometimes|nullable|date|before_or_equal:today',
        ]);

        $intervencion = $this->expedienteService->corregirFechas($this->intervencion($id), $validated, $request->user());

        return $this->respuesta($intervencion, 'Fechas corregidas');
    }

    private function intervencion(int $id): ExpedienteIntervencion
    {
        return ExpedienteIntervencion::with(['expediente', 'estado', 'responsable'])->findOrFail($id);
    }

    private function respuesta(ExpedienteIntervencion $intervencion, string $mensaje): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $mensaje,
            'data' => $intervencion->fresh(['expediente', 'estado', 'responsable', 'revisor', 'dependenciaDestino', 'colaboradores']),
        ]);
    }
}
