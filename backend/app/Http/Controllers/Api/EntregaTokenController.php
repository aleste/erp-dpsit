<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CertificadoFirma;
use App\Models\DispositivoFirma;
use App\Models\EntregaToken;
use App\Services\AuditService;
use App\Services\FirmaDigitalService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EntregaTokenController extends Controller
{
    public function __construct(
        protected FirmaDigitalService $firmaDigitalService
    ) {}

    /**
     * Listado de entregas con su certificado actual y filtros cruzados
     */
    public function index(Request $request): JsonResponse
    {
        $query = EntregaToken::with([
            'persona',
            'dispositivo',
            'ministerio',
            'certificadoActual',
        ]);

        if ($request->filled('persona_id')) {
            $query->where('persona_id', $request->persona_id);
        }

        if ($request->filled('ministerio_id')) {
            $query->where('ministerio_id', $request->ministerio_id);
        }

        if ($request->boolean('sin_organismo')) {
            $query->whereNull('ministerio_id');
        }

        if ($request->filled('organismo_externo')) {
            $query->where('organismo_externo', 'ILIKE', '%'.trim($request->organismo_externo).'%');
        }

        if ($request->filled('estado_entrega')) {
            $query->where('estado', $request->estado_entrega);
        }

        if ($request->filled('estado_certificado') && in_array($request->estado_certificado, CertificadoFirma::ESTADOS_CERTIFICADO, true)) {
            $estado = $request->estado_certificado;
            $query->whereHas('certificadoActual', fn ($q) => $q->estadoCertificado($estado));
        }

        if ($request->filled('vencimiento_desde')) {
            $desde = $request->vencimiento_desde;
            $query->whereHas('certificadoActual', fn ($q) => $q->whereDate('fecha_vencimiento', '>=', $desde));
        }

        if ($request->filled('vencimiento_hasta')) {
            $hasta = $request->vencimiento_hasta;
            $query->whereHas('certificadoActual', fn ($q) => $q->whereDate('fecha_vencimiento', '<=', $hasta));
        }

        if ($request->filled('recordatorio_estado')) {
            $estadoRecordatorio = $request->recordatorio_estado;
            $query->whereHas('certificadoActual', fn ($q) => $q->where('recordatorio_estado', $estadoRecordatorio));
        }

        // Búsqueda rápida por persona (nombre, CUIL, documento) o número de serie del dispositivo
        if ($request->filled('q')) {
            $term = trim($request->q);
            $query->where(function ($q) use ($term) {
                $q->whereHas('persona', function ($p) use ($term) {
                    $p->where('apellido', 'ILIKE', "%{$term}%")
                        ->orWhere('nombre', 'ILIKE', "%{$term}%")
                        ->orWhere('cuil_cuit', 'LIKE', "%{$term}%")
                        ->orWhere('numero_documento', 'ILIKE', "%{$term}%");
                })->orWhereHas('dispositivo', fn ($d) => $d->where('numero_serie', 'ILIKE', "%{$term}%"));
            });
        }

        $perPage = min(max((int) $request->get('per_page', 25), 1), 100);
        $entregas = $query->orderByDesc('fecha_entrega')->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $entregas,
            'stats' => $this->stats(),
        ]);
    }

    /**
     * Detalle de la entrega con todo su historial de certificados
     */
    public function show(int $id): JsonResponse
    {
        $entrega = EntregaToken::with([
            'persona',
            'dispositivo',
            'ministerio',
            'registradoPor',
            'certificados' => fn ($q) => $q->orderByDesc('fecha_inicio')->orderByDesc('id'),
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $entrega,
        ]);
    }

    /**
     * Registra la entrega de un token: dispositivo (nuevo o existente), entrega y primer certificado
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'persona_id' => 'required|exists:personas,id',
            'ministerio_id' => 'nullable|exists:ministerios,id',
            'organismo_externo' => 'nullable|string|max:255|prohibits:ministerio_id',
            'cargo_funcion' => 'nullable|string|max:150',
            'fecha_entrega' => 'required|date',
            'observaciones' => 'nullable|string',
            'reemplaza_entrega_id' => 'nullable|exists:entregas_token,id',

            'dispositivo_firma_id' => 'nullable|exists:dispositivos_firma,id',
            'dispositivo' => 'required_without:dispositivo_firma_id|array',
            'dispositivo.tipo_dispositivo' => ['required_without:dispositivo_firma_id', Rule::in(DispositivoFirma::TIPOS)],
            'dispositivo.marca' => 'nullable|string|max:100',
            'dispositivo.modelo' => 'nullable|string|max:100',
            'dispositivo.numero_serie' => 'nullable|string|max:100|unique:dispositivos_firma,numero_serie',
            'dispositivo.observaciones' => 'nullable|string',

            'certificado' => 'nullable|array',
            'certificado.fecha_inicio' => 'nullable|date',
            'certificado.vencimiento_manual' => 'boolean',
            'certificado.fecha_vencimiento' => 'nullable|required_if:certificado.vencimiento_manual,true|date',
            'certificado.recordatorio_manual' => 'boolean',
            'certificado.fecha_recordatorio' => 'nullable|required_if:certificado.recordatorio_manual,true|date',
        ]);

        if (! empty($validated['dispositivo_firma_id'])) {
            $dispositivo = DispositivoFirma::findOrFail($validated['dispositivo_firma_id']);
            if (! in_array($dispositivo->estado, ['DISPONIBLE', 'DEVUELTO'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => "El dispositivo seleccionado no está disponible (estado: {$dispositivo->estado}).",
                ], 422);
            }
        }

        if (! empty($validated['reemplaza_entrega_id'])) {
            $reemplazada = EntregaToken::findOrFail($validated['reemplaza_entrega_id']);
            if ($reemplazada->persona_id !== (int) $validated['persona_id'] || $reemplazada->estado !== EntregaToken::ESTADO_ASIGNADA) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se puede reemplazar una entrega asignada de la misma persona.',
                ], 422);
            }
        }

        $entrega = DB::transaction(function () use ($validated) {
            // Reemplazo: se cierra la entrega anterior y se cancelan sus recordatorios pendientes
            if (! empty($validated['reemplaza_entrega_id'])) {
                $anterior = EntregaToken::findOrFail($validated['reemplaza_entrega_id']);
                $this->cerrarEntrega($anterior, 'REEMPLAZADA', $validated['fecha_entrega'], 'Reemplazada por una nueva entrega', 'DEVUELTO');
            }

            $dispositivo = ! empty($validated['dispositivo_firma_id'])
                ? DispositivoFirma::findOrFail($validated['dispositivo_firma_id'])
                : DispositivoFirma::create($validated['dispositivo']);
            $dispositivo->update(['estado' => 'ASIGNADO']);

            $entrega = EntregaToken::create([
                'persona_id' => $validated['persona_id'],
                'dispositivo_firma_id' => $dispositivo->id,
                'ministerio_id' => $validated['ministerio_id'] ?? null,
                'organismo_externo' => $validated['organismo_externo'] ?? null,
                'cargo_funcion' => $validated['cargo_funcion'] ?? null,
                'fecha_entrega' => $validated['fecha_entrega'],
                'estado' => EntregaToken::ESTADO_ASIGNADA,
                'observaciones' => $validated['observaciones'] ?? null,
                'registrado_por_user_id' => Auth::id(),
            ]);

            // Por defecto el certificado comienza el día de la entrega
            $datosCertificado = $validated['certificado'] ?? [];
            $datosCertificado['fecha_inicio'] = $datosCertificado['fecha_inicio'] ?? $validated['fecha_entrega'];

            $entrega->certificados()->create($this->firmaDigitalService->resolverFechas($datosCertificado));

            return $entrega;
        });

        AuditService::log('CREATE', 'FIRMA_DIGITAL', $entrega->id, null, $entrega->toArray(), 'Entrega de token de firma digital');

        return response()->json([
            'success' => true,
            'message' => 'Entrega de token registrada correctamente',
            'data' => $entrega->load(['persona', 'dispositivo', 'ministerio', 'certificadoActual']),
        ], 201);
    }

    /**
     * Modifica los datos de la entrega (no el dispositivo ni el certificado)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $entrega = EntregaToken::findOrFail($id);
        $anterior = $entrega->toArray();

        $validated = $request->validate([
            'ministerio_id' => 'nullable|exists:ministerios,id',
            'organismo_externo' => 'nullable|string|max:255|prohibits:ministerio_id',
            'cargo_funcion' => 'nullable|string|max:150',
            'fecha_entrega' => 'required|date',
            'observaciones' => 'nullable|string',
        ]);

        $validated['ministerio_id'] = $validated['ministerio_id'] ?? null;
        $validated['organismo_externo'] = $validated['organismo_externo'] ?? null;

        $entrega->update($validated);
        AuditService::log('UPDATE', 'FIRMA_DIGITAL', $entrega->id, $anterior, $entrega->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Entrega actualizada correctamente',
            'data' => $entrega->load(['persona', 'dispositivo', 'ministerio', 'certificadoActual']),
        ]);
    }

    /**
     * Finaliza una entrega: devolución, extravío o revocación del dispositivo
     */
    public function finalizar(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'estado' => 'required|in:DEVUELTA,EXTRAVIADA,REVOCADA',
            'fecha_fin' => 'required|date',
            'motivo_fin' => 'nullable|string',
            'estado_dispositivo' => 'nullable|in:DISPONIBLE,DEVUELTO,EXTRAVIADO,DADO_DE_BAJA',
        ]);

        $entrega = EntregaToken::findOrFail($id);

        if ($entrega->estado !== EntregaToken::ESTADO_ASIGNADA) {
            return response()->json([
                'success' => false,
                'message' => 'La entrega ya se encuentra finalizada.',
            ], 422);
        }

        if ($entrega->fecha_entrega->gt(Carbon::parse($validated['fecha_fin']))) {
            return response()->json([
                'success' => false,
                'message' => 'La fecha de finalización no puede ser anterior a la fecha de entrega.',
            ], 422);
        }

        $anterior = $entrega->toArray();

        $estadoDispositivo = $validated['estado_dispositivo']
            ?? ($validated['estado'] === 'EXTRAVIADA' ? 'EXTRAVIADO' : 'DEVUELTO');

        DB::transaction(fn () => $this->cerrarEntrega(
            $entrega,
            $validated['estado'],
            $validated['fecha_fin'],
            $validated['motivo_fin'] ?? null,
            $estadoDispositivo
        ));

        AuditService::log('UPDATE', 'FIRMA_DIGITAL', $entrega->id, $anterior, $entrega->fresh()->toArray(), "Entrega finalizada: {$validated['estado']}");

        return response()->json([
            'success' => true,
            'message' => 'Entrega finalizada correctamente',
            'data' => $entrega->fresh(['persona', 'dispositivo', 'ministerio', 'certificadoActual']),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $entrega = EntregaToken::with('dispositivo')->findOrFail($id);
        $anterior = $entrega->toArray();

        DB::transaction(function () use ($entrega) {
            $this->cancelarRecordatoriosPendientes($entrega);

            if ($entrega->estado === EntregaToken::ESTADO_ASIGNADA && $entrega->dispositivo) {
                $entrega->dispositivo->update(['estado' => 'DISPONIBLE']);
            }

            $entrega->delete();
        });

        AuditService::log('DELETE', 'FIRMA_DIGITAL', $id, $anterior, null);

        return response()->json([
            'success' => true,
            'message' => 'Entrega eliminada correctamente',
        ]);
    }

    private function cerrarEntrega(EntregaToken $entrega, string $estado, string $fechaFin, ?string $motivo, string $estadoDispositivo): void
    {
        $entrega->update([
            'estado' => $estado,
            'fecha_fin' => $fechaFin,
            'motivo_fin' => $motivo,
        ]);

        $entrega->dispositivo?->update(['estado' => $estadoDispositivo]);

        $this->cancelarRecordatoriosPendientes($entrega);
    }

    private function cancelarRecordatoriosPendientes(EntregaToken $entrega): void
    {
        $entrega->certificados()
            ->whereIn('recordatorio_estado', [CertificadoFirma::RECORDATORIO_PENDIENTE, CertificadoFirma::RECORDATORIO_ERROR])
            ->update(['recordatorio_estado' => CertificadoFirma::RECORDATORIO_CANCELADO]);
    }

    /**
     * Resumen para las tarjetas del módulo
     */
    private function stats(): array
    {
        $asignadas = fn () => EntregaToken::where('estado', EntregaToken::ESTADO_ASIGNADA);
        $porEstadoCertificado = fn (string $estado) => $asignadas()
            ->whereHas('certificadoActual', fn ($q) => $q->estadoCertificado($estado))
            ->count();

        $recordatorios = CertificadoFirma::select('recordatorio_estado', DB::raw('count(*) as total'))
            ->groupBy('recordatorio_estado')
            ->pluck('total', 'recordatorio_estado');

        return [
            'asignados' => $asignadas()->count(),
            'vigentes' => $porEstadoCertificado(CertificadoFirma::VIGENTE),
            'proximos_a_vencer' => $porEstadoCertificado(CertificadoFirma::PROXIMO_A_VENCER),
            'vencidos' => $porEstadoCertificado(CertificadoFirma::VENCIDO),
            'recordatorios' => [
                'pendientes' => (int) ($recordatorios[CertificadoFirma::RECORDATORIO_PENDIENTE] ?? 0),
                'enviados' => (int) ($recordatorios[CertificadoFirma::RECORDATORIO_ENVIADO] ?? 0),
                'con_error' => (int) ($recordatorios[CertificadoFirma::RECORDATORIO_ERROR] ?? 0),
                'sin_email' => (int) ($recordatorios[CertificadoFirma::RECORDATORIO_SIN_EMAIL] ?? 0),
            ],
            'dias_anticipacion' => CertificadoFirma::diasAnticipacion(),
        ];
    }
}
