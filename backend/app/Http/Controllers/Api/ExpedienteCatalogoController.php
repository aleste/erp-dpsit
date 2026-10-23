<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DependenciaDestino;
use App\Models\ExpedienteEstado;
use App\Models\ExpedienteIntervencion;
use App\Models\ExpedienteTipoRequerimiento;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Catálogos configurables del módulo (solo ADMINISTRADOR): estados, tipos de requerimiento y destinos.
 * No se borran: se desactivan, para no romper el historial que los referencia.
 */
class ExpedienteCatalogoController extends Controller
{
    private const MODELOS = [
        'estados' => ExpedienteEstado::class,
        'requerimientos' => ExpedienteTipoRequerimiento::class,
        'destinos' => DependenciaDestino::class,
    ];

    public function store(Request $request, string $catalogo): JsonResponse
    {
        $modelo = $this->modelo($catalogo);
        $registro = $modelo::create($request->validate($this->reglas($catalogo)));
        AuditService::log('CREATE', 'EXPEDIENTES_CATALOGOS', $registro->id, null, $registro->toArray(), "Alta en catálogo {$catalogo}");

        return response()->json(['success' => true, 'message' => 'Registro creado', 'data' => $registro], 201);
    }

    public function update(Request $request, string $catalogo, int $id): JsonResponse
    {
        $modelo = $this->modelo($catalogo);
        /** @var Model $registro */
        $registro = $modelo::findOrFail($id);
        $anterior = $registro->toArray();
        $validated = $request->validate($this->reglas($catalogo, $id));

        // Un estado en uso por intervenciones abiertas no puede pasar a final ni desactivarse
        if ($catalogo === 'estados') {
            $enUso = ExpedienteIntervencion::where('estado_id', $id)->where('en_area', true)->exists();
            if ($enUso && ((($validated['es_final'] ?? false) && ! $registro->es_final) || (($validated['activo'] ?? true) === false))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hay expedientes en el área con este estado: no puede marcarse como final ni desactivarse.',
                ], 422);
            }
        }

        $registro->update($validated);
        AuditService::log('UPDATE', 'EXPEDIENTES_CATALOGOS', $registro->id, $anterior, $registro->toArray(), "Modificación en catálogo {$catalogo}");

        return response()->json(['success' => true, 'message' => 'Registro actualizado', 'data' => $registro]);
    }

    private function modelo(string $catalogo): string
    {
        abort_unless(isset(self::MODELOS[$catalogo]), 404, 'Catálogo inexistente');

        return self::MODELOS[$catalogo];
    }

    private function reglas(string $catalogo, ?int $id = null): array
    {
        $tabla = (new (self::MODELOS[$catalogo]))->getTable();
        $base = [
            'nombre' => ['required', 'string', 'max:255', Rule::unique($tabla, 'nombre')->ignore($id)],
            'activo' => 'boolean',
        ];

        return match ($catalogo) {
            'estados' => $base + [
                'orden' => 'integer|min:0|max:999',
                'color' => 'nullable|string|max:20',
                'es_final' => 'boolean',
                'requiere_documento' => 'boolean',
                'exime_documento' => 'boolean',
            ],
            'requerimientos' => $base + ['grupo' => ['nullable', Rule::in(ExpedienteTipoRequerimiento::GRUPOS)]],
            default => $base,
        };
    }
}
