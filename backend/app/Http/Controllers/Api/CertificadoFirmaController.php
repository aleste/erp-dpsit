<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CertificadoFirma;
use App\Models\EntregaToken;
use App\Services\AuditService;
use App\Services\FirmaDigitalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CertificadoFirmaController extends Controller
{
    public function __construct(
        protected FirmaDigitalService $firmaDigitalService
    ) {}

    /**
     * Seguimiento de certificados y recordatorios (pendientes, enviados, con error)
     */
    public function index(Request $request): JsonResponse
    {
        $query = CertificadoFirma::with(['entrega.persona', 'entrega.dispositivo', 'entrega.ministerio']);

        // Por defecto, solo el certificado más reciente de cada entrega
        if (! $request->boolean('incluir_historicos')) {
            $query->actuales();
        }

        if ($request->filled('recordatorio_estado')) {
            $query->where('recordatorio_estado', $request->recordatorio_estado);
        }

        if ($request->filled('estado_certificado') && in_array($request->estado_certificado, CertificadoFirma::ESTADOS_CERTIFICADO, true)) {
            $query->estadoCertificado($request->estado_certificado);
        }

        if ($request->filled('recordatorio_desde')) {
            $query->whereDate('fecha_recordatorio', '>=', $request->recordatorio_desde);
        }

        if ($request->filled('recordatorio_hasta')) {
            $query->whereDate('fecha_recordatorio', '<=', $request->recordatorio_hasta);
        }

        if ($request->filled('ministerio_id')) {
            $ministerioId = $request->ministerio_id;
            $query->whereHas('entrega', fn ($q) => $q->where('ministerio_id', $ministerioId));
        }

        if ($request->filled('q')) {
            $term = trim($request->q);
            $query->whereHas('entrega.persona', function ($p) use ($term) {
                $p->where('apellido', 'ILIKE', "%{$term}%")
                    ->orWhere('nombre', 'ILIKE', "%{$term}%")
                    ->orWhere('numero_documento', 'ILIKE', "%{$term}%");
            });
        }

        $perPage = min(max((int) $request->get('per_page', 25), 1), 100);
        $certificados = $query->orderBy('fecha_recordatorio')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $certificados,
        ]);
    }

    /**
     * Renovación del certificado sobre el mismo token (nuevo certificado en la misma entrega)
     */
    public function store(Request $request, int $entregaId): JsonResponse
    {
        $validated = $this->validarFechas($request, true);
        $entrega = EntregaToken::findOrFail($entregaId);

        if ($entrega->estado !== EntregaToken::ESTADO_ASIGNADA) {
            return response()->json([
                'success' => false,
                'message' => 'Solo se puede renovar el certificado de una entrega asignada.',
            ], 422);
        }

        $certificado = DB::transaction(function () use ($entrega, $validated) {
            // El certificado anterior deja de requerir recordatorio
            $entrega->certificados()
                ->whereIn('recordatorio_estado', [CertificadoFirma::RECORDATORIO_PENDIENTE, CertificadoFirma::RECORDATORIO_ERROR])
                ->update(['recordatorio_estado' => CertificadoFirma::RECORDATORIO_CANCELADO]);

            return $entrega->certificados()->create(array_merge(
                $this->firmaDigitalService->resolverFechas($validated),
                ['observaciones' => $validated['observaciones'] ?? null]
            ));
        });

        AuditService::log('CREATE', 'FIRMA_DIGITAL', $entrega->id, null, $certificado->toArray(), 'Renovación de certificado digital');

        return response()->json([
            'success' => true,
            'message' => 'Certificado renovado correctamente',
            'data' => $certificado,
        ], 201);
    }

    /**
     * Corrección de fechas del certificado (inicio, vencimiento o recordatorio manual)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $certificado = CertificadoFirma::findOrFail($id);
        $anterior = $certificado->toArray();
        $validated = $this->validarFechas($request, false);

        $certificado->update(array_merge(
            $this->firmaDigitalService->resolverFechas($validated, $certificado),
            ['observaciones' => $validated['observaciones'] ?? $certificado->observaciones]
        ));

        AuditService::log('UPDATE', 'FIRMA_DIGITAL', $certificado->entrega_token_id, $anterior, $certificado->toArray(), 'Modificación de fechas del certificado');

        return response()->json([
            'success' => true,
            'message' => 'Certificado actualizado correctamente',
            'data' => $certificado->fresh(),
        ]);
    }

    /**
     * Vuelve a dejar pendiente el recordatorio (p. ej. tras corregir el email o ante un error)
     */
    public function reprogramarRecordatorio(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'fecha_recordatorio' => 'nullable|date',
        ]);

        $certificado = CertificadoFirma::with('entrega')->findOrFail($id);

        if ($certificado->entrega?->estado !== EntregaToken::ESTADO_ASIGNADA) {
            return response()->json([
                'success' => false,
                'message' => 'La entrega no está asignada: no corresponde enviar recordatorios.',
            ], 422);
        }

        $anterior = $certificado->toArray();

        $certificado->update([
            'fecha_recordatorio' => $validated['fecha_recordatorio'] ?? $certificado->fecha_recordatorio,
            'recordatorio_manual' => ! empty($validated['fecha_recordatorio']) || $certificado->recordatorio_manual,
            'recordatorio_estado' => CertificadoFirma::RECORDATORIO_PENDIENTE,
            'recordatorio_intentos' => 0,
            'recordatorio_ultimo_error' => null,
            'recordatorio_enviado_at' => null,
        ]);

        AuditService::log('UPDATE', 'FIRMA_DIGITAL', $certificado->entrega_token_id, $anterior, $certificado->toArray(), 'Recordatorio reprogramado');

        return response()->json([
            'success' => true,
            'message' => 'Recordatorio reprogramado correctamente',
            'data' => $certificado->fresh(),
        ]);
    }

    /**
     * Parámetros de cálculo vigentes (para la vista previa de fechas en la interfaz)
     */
    public function parametros(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->firmaDigitalService->parametros(),
        ]);
    }

    private function validarFechas(Request $request, bool $inicioObligatorio): array
    {
        return $request->validate([
            'fecha_inicio' => ($inicioObligatorio ? 'required' : 'nullable').'|date',
            'vencimiento_manual' => 'boolean',
            'fecha_vencimiento' => 'nullable|required_if:vencimiento_manual,true|date',
            'recordatorio_manual' => 'boolean',
            'fecha_recordatorio' => 'nullable|required_if:recordatorio_manual,true|date',
            'observaciones' => 'nullable|string',
        ]);
    }
}
