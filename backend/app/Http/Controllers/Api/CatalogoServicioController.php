<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CatalogoServicio;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogoServicioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CatalogoServicio::withCount('serviciosAreas');

        if ($request->filled('activo')) {
            $query->where('activo', filter_var($request->activo, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('categoria')) {
            $query->where('categoria', $request->categoria);
        }

        $servicios = $query->orderBy('categoria')->orderBy('nombre')->get();

        return response()->json([
            'success' => true,
            'data' => $servicios,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $servicio = CatalogoServicio::with(['serviciosAreas.areaTi.ministerio'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $servicio,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'codigo' => 'required|string|max:50|unique:catalogo_servicios,codigo',
            'nombre' => 'required|string|max:150',
            'categoria' => 'required|string|max:50',
            'descripcion' => 'nullable|string',
            'activo' => 'boolean',
        ]);

        $servicio = CatalogoServicio::create($validated);
        AuditService::log('CREATE', 'CATALOGO_SERVICIOS', $servicio->id, null, $servicio->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Servicio registrado en el catálogo provincial',
            'data' => $servicio,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $servicio = CatalogoServicio::findOrFail($id);
        $anterior = $servicio->toArray();

        $validated = $request->validate([
            'codigo' => "required|string|max:50|unique:catalogo_servicios,codigo,{$id}",
            'nombre' => 'required|string|max:150',
            'categoria' => 'required|string|max:50',
            'descripcion' => 'nullable|string',
            'activo' => 'boolean',
        ]);

        $servicio->update($validated);
        AuditService::log('UPDATE', 'CATALOGO_SERVICIOS', $servicio->id, $anterior, $servicio->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Servicio actualizado en el catálogo',
            'data' => $servicio,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $servicio = CatalogoServicio::findOrFail($id);
        if ($servicio->serviciosAreas()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el servicio del catálogo porque existen áreas TI utilizándolo.',
            ], 422);
        }

        $anterior = $servicio->toArray();
        $servicio->delete();
        AuditService::log('DELETE', 'CATALOGO_SERVICIOS', $id, $anterior, null);

        return response()->json([
            'success' => true,
            'message' => 'Servicio eliminado del catálogo',
        ]);
    }
}
