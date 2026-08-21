<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TipoRol;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TipoRolController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TipoRol::withCount(['asignaciones' => function ($q) {
            $q->vigente();
        }]);

        if ($request->filled('activo')) {
            $query->where('activo', filter_var($request->activo, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('nivel_responsabilidad')) {
            $query->where('nivel_responsabilidad', $request->nivel_responsabilidad);
        }

        $roles = $query->orderBy('nombre')->get();

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $rol = TipoRol::withCount('asignaciones')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $rol,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'codigo' => 'required|string|max:50|unique:tipos_rol,codigo',
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string',
            'nivel_responsabilidad' => 'required|in:EJECUTIVO,TACTICO,OPERATIVO',
            'es_maxima_autoridad_default' => 'boolean',
            'activo' => 'boolean',
        ]);

        $rol = TipoRol::create($validated);
        AuditService::log('CREATE', 'TIPOS_ROL', $rol->id, null, $rol->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Tipo de rol creado con éxito',
            'data' => $rol,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $rol = TipoRol::findOrFail($id);
        $anterior = $rol->toArray();

        $validated = $request->validate([
            'codigo' => "required|string|max:50|unique:tipos_rol,codigo,{$id}",
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string',
            'nivel_responsabilidad' => 'required|in:EJECUTIVO,TACTICO,OPERATIVO',
            'es_maxima_autoridad_default' => 'boolean',
            'activo' => 'boolean',
        ]);

        $rol->update($validated);
        AuditService::log('UPDATE', 'TIPOS_ROL', $rol->id, $anterior, $rol->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Tipo de rol actualizado con éxito',
            'data' => $rol,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $rol = TipoRol::findOrFail($id);
        if ($rol->asignaciones()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No es posible eliminar el rol porque posee asignaciones históricas asociadas.',
            ], 422);
        }

        $anterior = $rol->toArray();
        $rol->delete();
        AuditService::log('DELETE', 'TIPOS_ROL', $id, $anterior, null);

        return response()->json([
            'success' => true,
            'message' => 'Tipo de rol eliminado con éxito',
        ]);
    }
}
