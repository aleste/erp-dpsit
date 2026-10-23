<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DependenciaDestino;
use App\Models\Expediente;
use App\Models\ExpedienteEstado;
use App\Models\ExpedienteIntervencion;
use App\Models\ExpedienteMovimiento;
use App\Models\ExpedienteTipoRequerimiento;
use App\Models\User;
use App\Models\UserModulo;
use App\Services\AuditService;
use App\Services\ExpedienteService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpedienteController extends Controller
{
    public function __construct(
        protected ExpedienteService $expedienteService
    ) {}

    /**
     * Listado de intervenciones (cada pase por el área, como las filas de la planilla) con filtros cruzados
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->get('per_page', 25), 1), 100);
        $resultado = $this->consulta($request)->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $resultado,
        ]);
    }

    /**
     * Tablero del área: situación actual, prioridades por antigüedad y últimos movimientos
     */
    public function tablero(Request $request): JsonResponse
    {
        $hoy = now()->toDateString();
        $umbral = (int) config('expedientes.umbral_dias_alerta', 30);
        $enArea = fn () => ExpedienteIntervencion::enArea();

        $porEstado = ExpedienteEstado::where('es_final', false)->orderBy('orden')->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'nombre' => $e->nombre,
                'color' => $e->color,
                'cantidad' => $enArea()->where('estado_id', $e->id)->count(),
            ]);

        $prioridad = ExpedienteIntervencion::with(['expediente.ministerio', 'estado', 'responsable'])
            ->enArea()
            ->orderByRaw("CASE WHEN prioridad = 'ALTA' THEN 0 ELSE 1 END")
            ->orderBy('fecha_ingreso')
            ->take(15)
            ->get();

        $ultimos = ExpedienteMovimiento::with(['intervencion.expediente', 'usuario', 'estadoNuevo'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->take(15)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'totales' => [
                    'en_area' => $enArea()->count(),
                    'sin_responsable' => $enArea()->whereNull('responsable_id')->count(),
                    'mis_expedientes' => $enArea()->where(fn ($q) => $q->where('responsable_id', $request->user()->id)
                        ->orWhereHas('colaboradores', fn ($c) => $c->where('users.id', $request->user()->id)))->count(),
                    'accion_vencida' => ExpedienteIntervencion::accionVencida()->count(),
                    'plazo_vencido' => $enArea()->whereDate('fecha_vencimiento', '<', $hoy)->count(),
                    'demorados' => $enArea()->whereDate('fecha_ingreso', '<=', now()->subDays($umbral)->toDateString())->count(),
                    'egresos_mes' => ExpedienteIntervencion::where('en_area', false)
                        ->whereBetween('fecha_egreso', [now()->startOfMonth()->toDateString(), $hoy])->count(),
                ],
                'umbral_dias_alerta' => $umbral,
                'por_estado' => $porEstado,
                'prioridad' => $prioridad,
                'ultimos_movimientos' => $ultimos,
            ],
        ]);
    }

    /**
     * Ficha completa: expediente, todas sus intervenciones y el historial de cada una
     */
    public function show(int $id): JsonResponse
    {
        $expediente = Expediente::with([
            'ministerio',
            'intervenciones' => fn ($q) => $q->orderByDesc('numero_intervencion')->with([
                'estado', 'tipoRequerimiento', 'responsable', 'revisor', 'dependenciaDestino', 'colaboradores',
                'movimientos' => fn ($m) => $m->orderByDesc('fecha')->orderByDesc('id')->with(['usuario', 'estadoAnterior', 'estadoNuevo']),
            ]),
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $expediente,
        ]);
    }

    /**
     * Verifica si un número ya existe (reemplaza el resaltado de duplicados de la planilla)
     */
    public function buscarNumero(Request $request): JsonResponse
    {
        $request->validate(['numero' => 'required|string|max:100']);
        $clave = Expediente::claveBusqueda($request->numero);

        $expediente = Expediente::with(['ministerio', 'intervencionActual.estado'])
            ->where('numero_busqueda', $clave)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'existe' => (bool) $expediente,
                'expediente' => $expediente,
                'en_area' => (bool) $expediente?->intervencionActual?->en_area,
                'proxima_intervencion' => $expediente ? (int) $expediente->intervenciones()->withTrashed()->max('numero_intervencion') + 1 : 1,
                'numero_normalizado' => Expediente::normalizarNumero($request->numero),
            ],
        ]);
    }

    /**
     * Ingreso al área (expediente nuevo o reingreso)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'numero' => 'required|string|max:100',
            'fecha_ingreso' => 'required|date|before_or_equal:today',
            'caratula' => 'nullable|string|max:2000',
            'ministerio_id' => 'nullable|exists:ministerios,id',
            'tipo_requerimiento_id' => 'nullable|exists:expediente_tipos_requerimiento,id',
            'descripcion' => 'nullable|string|max:2000',
            'estado_id' => 'nullable|exists:expediente_estados,id',
            'responsable_id' => 'nullable|exists:users,id',
            'colaboradores' => 'nullable|array',
            'colaboradores.*' => 'integer|exists:users,id',
            'prioridad' => ['nullable', Rule::in(ExpedienteIntervencion::PRIORIDADES)],
            'proxima_accion' => 'nullable|string|max:255',
            'fecha_proxima_accion' => 'nullable|date',
            'fecha_vencimiento' => 'nullable|date',
            'observaciones' => 'nullable|string|max:2000',
        ]);

        $intervencion = $this->expedienteService->registrarIngreso($validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => $intervencion->numero_intervencion > 1
                ? "Reingreso registrado ({$intervencion->numero_intervencion}ª intervención)"
                : 'Expediente ingresado correctamente',
            'data' => $intervencion->load(['expediente', 'estado']),
        ], 201);
    }

    /**
     * Baja lógica de un expediente cargado por error (solo ADMINISTRADOR); el historial se conserva
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['motivo' => 'required|string|max:500']);
        $expediente = Expediente::findOrFail($id);
        $anterior = $expediente->toArray();

        // Las intervenciones también se dan de baja (lógica) para que no aparezcan en listados ni tableros;
        // los movimientos no se tocan
        DB::transaction(function () use ($expediente) {
            $expediente->intervenciones()->get()->each->delete();
            $expediente->delete();
        });

        AuditService::log('DELETE', 'EXPEDIENTES', $id, $anterior, null, 'Baja lógica: '.$validated['motivo']);

        return response()->json([
            'success' => true,
            'message' => 'Expediente dado de baja',
        ]);
    }

    /**
     * Exportación CSV con los mismos filtros del listado (requiere token: se descarga con fetch desde la UI)
     */
    public function exportar(Request $request): StreamedResponse
    {
        $consulta = $this->consulta($request);
        AuditService::log('EXPORT', 'EXPEDIENTES', null, null, $request->query(), 'Exportación de expedientes a CSV');

        return response()->streamDownload(function () use ($consulta) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Ingreso', 'Egreso', 'Nº Expediente', 'Intervención', 'Organismo', 'Carátula', 'Motivo',
                'Responsable', 'Requerimiento', 'Estado', 'A revisión', 'Revisor', 'Firma', 'Remitido a',
                'Documento de respuesta', 'Días en el área', 'Próxima acción', 'Fecha próxima acción', 'Observaciones'], ';');

            $consulta->chunk(500, function ($filas) use ($out) {
                foreach ($filas as $i) {
                    fputcsv($out, [
                        $i->fecha_ingreso?->format('d/m/Y'), $i->fecha_egreso?->format('d/m/Y'),
                        $i->expediente?->numero, $i->numero_intervencion, $i->expediente?->ministerio?->nombre,
                        $i->expediente?->caratula, $i->descripcion, $i->responsable?->name, $i->tipoRequerimiento?->nombre,
                        $i->estado?->nombre, $i->fecha_envio_revision?->format('d/m/Y'), $i->revisor?->name,
                        $i->fecha_firma?->format('d/m/Y'), $i->dependenciaDestino?->nombre, $i->documento_respuesta,
                        $i->dias_en_area, $i->proxima_accion, $i->fecha_proxima_accion?->format('d/m/Y'), $i->observaciones,
                    ], ';');
                }
            });
            fclose($out);
        }, 'expedientes_'.now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Catálogos y usuarios del módulo para los selectores de la interfaz
     */
    public function opciones(): JsonResponse
    {
        $integrantes = User::with('modulos')->where('activo', true)->orderBy('name')->get()
            ->filter(fn ($u) => $u->puedeEnModulo(UserModulo::EXPEDIENTES, 'OPERADOR'))
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'rol' => $u->rolEnModulo(UserModulo::EXPEDIENTES)])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'estados' => ExpedienteEstado::orderBy('orden')->get(),
                'tipos_requerimiento' => ExpedienteTipoRequerimiento::orderBy('nombre')->get(),
                'destinos' => DependenciaDestino::orderBy('nombre')->get(['id', 'nombre', 'activo']),
                'integrantes' => $integrantes,
                'umbral_dias_alerta' => (int) config('expedientes.umbral_dias_alerta', 30),
            ],
        ]);
    }

    private function consulta(Request $request): Builder
    {
        $query = ExpedienteIntervencion::with([
            'expediente.ministerio', 'estado', 'tipoRequerimiento', 'responsable', 'revisor', 'dependenciaDestino', 'colaboradores',
        ]);

        match ($request->get('situacion', 'EN_AREA')) {
            'EN_AREA' => $query->where('en_area', true),
            'EGRESADAS' => $query->where('en_area', false),
            default => null,
        };

        // Número: tolera espacios, guiones y mayúsculas; también busca en carátula, motivo y documento
        if ($request->filled('q')) {
            $term = trim($request->q);
            $clave = Expediente::claveBusqueda($term);
            $query->where(function ($q) use ($term, $clave) {
                $q->whereHas('expediente', function ($e) use ($term, $clave) {
                    $e->where('caratula', 'ILIKE', "%{$term}%");
                    if ($clave !== '') {
                        $e->orWhere('numero_busqueda', 'LIKE', "%{$clave}%");
                    }
                })->orWhere('descripcion', 'ILIKE', "%{$term}%")
                    ->orWhere('documento_respuesta', 'ILIKE', "%{$term}%");
            });
        }

        if ($request->filled('responsable_id')) {
            $request->responsable_id === 'sin'
                ? $query->whereNull('responsable_id')
                : $query->where('responsable_id', $request->responsable_id);
        }

        if ($request->boolean('mis')) {
            $userId = $request->user()->id;
            $query->where(fn ($q) => $q->where('responsable_id', $userId)
                ->orWhereHas('colaboradores', fn ($c) => $c->where('users.id', $userId)));
        }

        foreach (['estado_id', 'tipo_requerimiento_id', 'revisor_id', 'prioridad', 'dependencia_destino_id'] as $campo) {
            if ($request->filled($campo)) {
                $query->where($campo, $request->get($campo));
            }
        }

        if ($request->filled('ministerio_id')) {
            $ministerioId = $request->ministerio_id;
            $query->whereHas('expediente', fn ($e) => $e->where('ministerio_id', $ministerioId));
        }

        foreach (['ingreso' => 'fecha_ingreso', 'egreso' => 'fecha_egreso', 'vencimiento' => 'fecha_vencimiento'] as $prefijo => $columna) {
            if ($request->filled("{$prefijo}_desde")) {
                $query->whereDate($columna, '>=', $request->get("{$prefijo}_desde"));
            }
            if ($request->filled("{$prefijo}_hasta")) {
                $query->whereDate($columna, '<=', $request->get("{$prefijo}_hasta"));
            }
        }

        if ($request->boolean('accion_vencida')) {
            $query->accionVencida();
        }

        if ($request->boolean('plazo_vencido')) {
            $query->where('en_area', true)->whereDate('fecha_vencimiento', '<', now()->toDateString());
        }

        if ($request->filled('dias_min')) {
            $query->where('en_area', true)->whereDate('fecha_ingreso', '<=', now()->subDays((int) $request->dias_min)->toDateString());
        }

        match ($request->get('orden', 'antiguedad')) {
            'ingreso_desc' => $query->orderByDesc('fecha_ingreso')->orderByDesc('id'),
            'egreso_desc' => $query->orderByDesc('fecha_egreso')->orderByDesc('id'),
            default => $query->orderByRaw("CASE WHEN prioridad = 'ALTA' THEN 0 ELSE 1 END")->orderBy('fecha_ingreso')->orderBy('id'),
        };

        return $query;
    }
}
