<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DispositivoFirma;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DispositivoFirmaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DispositivoFirma::with('entregaActual.persona')->withCount('entregas');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->boolean('solo_disponibles')) {
            $query->whereIn('estado', ['DISPONIBLE', 'DEVUELTO']);
        }

        if ($request->filled('q')) {
            $term = trim($request->q);
            $query->where(function ($q) use ($term) {
                $q->where('numero_serie', 'ILIKE', "%{$term}%")
                    ->orWhere('marca', 'ILIKE', "%{$term}%")
                    ->orWhere('modelo', 'ILIKE', "%{$term}%");
            });
        }

        $perPage = min(max((int) $request->get('per_page', 25), 1), 100);
        $dispositivos = $query->orderBy('marca')->orderBy('modelo')->orderBy('numero_serie')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $dispositivos,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $dispositivo = DispositivoFirma::with([
            'entregas' => fn ($q) => $q->with(['persona', 'certificadoActual'])->orderByDesc('fecha_entrega'),
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $dispositivo,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->reglas());

        $dispositivo = DispositivoFirma::create($validated);
        AuditService::log('CREATE', 'FIRMA_DIGITAL', $dispositivo->id, null, $dispositivo->toArray(), 'Alta de dispositivo de firma');

        return response()->json([
            'success' => true,
            'message' => 'Dispositivo registrado correctamente',
            'data' => $dispositivo,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $dispositivo = DispositivoFirma::findOrFail($id);
        $anterior = $dispositivo->toArray();

        $validated = $request->validate($this->reglas($id));

        // El estado ASIGNADO solo lo administran las entregas
        if (isset($validated['estado']) && ($validated['estado'] === 'ASIGNADO') !== ($dispositivo->estado === 'ASIGNADO')) {
            return response()->json([
                'success' => false,
                'message' => 'El estado ASIGNADO se gestiona registrando o finalizando entregas.',
            ], 422);
        }

        $dispositivo->update($validated);
        AuditService::log('UPDATE', 'FIRMA_DIGITAL', $dispositivo->id, $anterior, $dispositivo->toArray(), 'Modificación de dispositivo de firma');

        return response()->json([
            'success' => true,
            'message' => 'Dispositivo actualizado correctamente',
            'data' => $dispositivo,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $dispositivo = DispositivoFirma::findOrFail($id);

        if ($dispositivo->entregas()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No es posible eliminar el dispositivo porque posee entregas registradas. Puede marcarlo como DADO_DE_BAJA.',
            ], 422);
        }

        $anterior = $dispositivo->toArray();
        $dispositivo->delete();
        AuditService::log('DELETE', 'FIRMA_DIGITAL', $id, $anterior, null, 'Baja de dispositivo de firma');

        return response()->json([
            'success' => true,
            'message' => 'Dispositivo eliminado correctamente',
        ]);
    }

    private function reglas(?int $id = null): array
    {
        return [
            'tipo_dispositivo' => ['required', Rule::in(DispositivoFirma::TIPOS)],
            'marca' => 'nullable|string|max:100',
            'modelo' => 'nullable|string|max:100',
            'numero_serie' => ['nullable', 'string', 'max:100', Rule::unique('dispositivos_firma', 'numero_serie')->ignore($id)],
            'estado' => ['nullable', Rule::in(DispositivoFirma::ESTADOS)],
            'observaciones' => 'nullable|string',
        ];
    }
}
