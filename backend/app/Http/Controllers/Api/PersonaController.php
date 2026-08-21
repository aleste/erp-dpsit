<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Persona;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Persona::with([
            'asignacionesVigentes.areaTi.ministerio',
            'asignacionesVigentes.tipoRol',
        ])->withCount(['asignaciones', 'serviciosComoReferente']);

        // Búsqueda rápida por nombre, apellido, cuil o emails
        if ($request->filled('q')) {
            $term = trim($request->q);
            $query->where(function ($q) use ($term) {
                $q->where('apellido', 'ILIKE', "%{$term}%")
                  ->orWhere('nombre', 'ILIKE', "%{$term}%")
                  ->orWhere('cuil_cuit', 'LIKE', "%{$term}%")
                  ->orWhere('email_principal', 'ILIKE', "%{$term}%")
                  ->orWhere('email_alternativo', 'ILIKE', "%{$term}%")
                  ->orWhere('titulo_profesional', 'ILIKE', "%{$term}%");
            });
        }

        // Filtro por Ministerio
        if ($request->filled('ministerio_id')) {
            $ministerioId = $request->ministerio_id;
            $query->whereHas('asignaciones.areaTi', function ($q) use ($ministerioId) {
                $q->where('ministerio_id', $ministerioId);
            });
        }

        // Filtro por Área TI
        if ($request->filled('area_ti_id')) {
            $areaTiId = $request->area_ti_id;
            $query->whereHas('asignaciones', function ($q) use ($areaTiId) {
                $q->where('area_ti_id', $areaTiId);
            });
        }

        // Filtro por Tipo de Rol
        if ($request->filled('tipo_rol_id')) {
            $tipoRolId = $request->tipo_rol_id;
            $query->whereHas('asignaciones', function ($q) use ($tipoRolId) {
                $q->where('tipo_rol_id', $tipoRolId);
            });
        }

        // Filtro solo autoridades vigentes
        if ($request->boolean('solo_autoridades')) {
            $query->whereHas('asignaciones', function ($q) {
                $q->vigente()->where('es_maxima_autoridad', true);
            });
        }

        // Filtro por estado de asignación
        if ($request->filled('estado_cargo')) {
            if ($request->estado_cargo === 'VIGENTE') {
                $query->has('asignacionesVigentes');
            } elseif ($request->estado_cargo === 'SIN_CARGO') {
                $query->doesntHave('asignacionesVigentes');
            }
        }

        $stats = [
            'total' => Persona::count(),
            'con_cargo_vigente' => Persona::has('asignacionesVigentes')->count(),
            'maximas_autoridades' => Persona::whereHas('asignaciones', function ($q) {
                $q->vigente()->where('es_maxima_autoridad', true);
            })->count(),
            'referentes_servicios' => Persona::has('serviciosComoReferente')->count(),
        ];

        $perPage = (int) $request->get('per_page', 20);
        $personas = $query->orderBy('apellido')->orderBy('nombre')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $personas,
            'stats' => $stats,
        ]);
    }

    /**
     * Búsqueda detallada por persona: Historial completo cronológico de cargos en toda la administración
     */
    public function show(int $id): JsonResponse
    {
        $persona = Persona::with([
            'asignaciones' => function ($q) {
                $q->with(['areaTi.ministerio', 'tipoRol', 'documentos'])
                  ->orderByDesc('fecha_inicio');
            },
            'serviciosComoReferente.servicioArea.catalogoServicio',
            'serviciosComoReferente.servicioArea.areaTi.ministerio',
            'documentos',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $persona,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cuil_cuit' => 'required|string|max:13|unique:personas,cuil_cuit',
            'apellido' => 'required|string|max:100',
            'nombre' => 'required|string|max:100',
            'email_principal' => 'required|email|max:255|unique:personas,email_principal',
            'email_alternativo' => 'nullable|email|max:255',
            'telefono_principal' => 'required|string|max:50',
            'telefono_alternativo' => 'nullable|string|max:50',
            'titulo_profesional' => 'nullable|string|max:150',
            'observaciones' => 'nullable|string',
            'activo' => 'boolean',
        ]);

        $persona = Persona::create($validated);
        AuditService::log('CREATE', 'PERSONAS', $persona->id, null, $persona->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Persona registrada correctamente en el padrón provincial',
            'data' => $persona,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $persona = Persona::findOrFail($id);
        $anterior = $persona->toArray();

        $validated = $request->validate([
            'cuil_cuit' => "required|string|max:13|unique:personas,cuil_cuit,{$id}",
            'apellido' => 'required|string|max:100',
            'nombre' => 'required|string|max:100',
            'email_principal' => "required|email|max:255|unique:personas,email_principal,{$id}",
            'email_alternativo' => 'nullable|email|max:255',
            'telefono_principal' => 'required|string|max:50',
            'telefono_alternativo' => 'nullable|string|max:50',
            'titulo_profesional' => 'nullable|string|max:150',
            'observaciones' => 'nullable|string',
            'activo' => 'boolean',
        ]);

        $persona->update($validated);
        AuditService::log('UPDATE', 'PERSONAS', $persona->id, $anterior, $persona->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Datos de la persona actualizados correctamente',
            'data' => $persona,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $persona = Persona::findOrFail($id);

        if ($persona->asignacionesVigentes()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No es posible eliminar la persona porque posee cargos/roles vigentes asignados. Finalice sus asignaciones previamente.',
            ], 422);
        }

        $anterior = $persona->toArray();
        $persona->delete();
        AuditService::log('DELETE', 'PERSONAS', $id, $anterior, null);

        return response()->json([
            'success' => true,
            'message' => 'Persona dada de baja del padrón exitosamente',
        ]);
    }
}
