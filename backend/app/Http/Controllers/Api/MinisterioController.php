<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ministerio;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MinisterioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Ministerio::withCount('areasTi')->with('autoridadVigente.persona');

        if ($request->filled('q')) {
            $term = $request->q;
            $query->where(function ($q) use ($term) {
                $q->where('nombre', 'ILIKE', "%{$term}%")
                    ->orWhere('sigla', 'ILIKE', "%{$term}%")
                    ->orWhere('codigo', 'ILIKE', "%{$term}%");
            });
        }

        if ($request->filled('activo')) {
            $query->where('activo', filter_var($request->activo, FILTER_VALIDATE_BOOLEAN));
        }

        $ministerios = $query->orderBy('orden_prioridad')->orderBy('nombre')->get();

        return response()->json([
            'success' => true,
            'data' => $ministerios,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $ministerio = Ministerio::with([
            'areasTi' => function ($q) {
                $q->with(['maximaAutoridadVigente.persona', 'maximaAutoridadVigente.tipoRol']);
            },
            'autoridadVigente.persona',
            'autoridades' => fn ($q) => $q->with('persona')->orderByDesc('fecha_inicio')->orderByDesc('id'),
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $ministerio,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'codigo' => 'required|string|max:20|unique:ministerios,codigo',
            'nombre' => 'required|string|max:255',
            'sigla' => 'nullable|string|max:50',
            'orden_prioridad' => 'nullable|integer',
            'activo' => 'boolean',
        ]);

        $ministerio = Ministerio::create($validated);
        AuditService::log('CREATE', 'MINISTERIOS', $ministerio->id, null, $ministerio->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Ministerio creado exitosamente',
            'data' => $ministerio,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ministerio = Ministerio::findOrFail($id);
        $anterior = $ministerio->toArray();

        $validated = $request->validate([
            'codigo' => "required|string|max:20|unique:ministerios,codigo,{$id}",
            'nombre' => 'required|string|max:255',
            'sigla' => 'nullable|string|max:50',
            'orden_prioridad' => 'nullable|integer',
            'activo' => 'boolean',
        ]);

        $ministerio->update($validated);
        AuditService::log('UPDATE', 'MINISTERIOS', $ministerio->id, $anterior, $ministerio->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Ministerio actualizado exitosamente',
            'data' => $ministerio,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $ministerio = Ministerio::findOrFail($id);

        if ($ministerio->areasTi()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No es posible eliminar el ministerio porque posee áreas TI vinculadas.',
            ], 422);
        }

        $anterior = $ministerio->toArray();
        $ministerio->delete();
        AuditService::log('DELETE', 'MINISTERIOS', $id, $anterior, null);

        return response()->json([
            'success' => true,
            'message' => 'Ministerio eliminado exitosamente',
        ]);
    }
}
