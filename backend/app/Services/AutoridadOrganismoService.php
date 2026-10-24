<?php

namespace App\Services;

use App\Models\AutoridadOrganismo;
use App\Models\Ministerio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Designación de la máxima autoridad de un organismo (D-32). A diferencia de
 * AreaTiController@designarAutoridad (DEF-09), cierra también las autoridades con cese futuro,
 * de modo que nunca quedan dos vigentes.
 */
class AutoridadOrganismoService
{
    public function designar(Ministerio $ministerio, array $datos): AutoridadOrganismo
    {
        $inicio = ! empty($datos['fecha_inicio']) ? Carbon::parse($datos['fecha_inicio']) : now()->startOfDay();
        $cese = $inicio->copy()->subDay()->toDateString();

        return DB::transaction(function () use ($ministerio, $datos, $inicio, $cese) {
            $abiertas = AutoridadOrganismo::where('ministerio_id', $ministerio->id)
                ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $inicio->toDateString()))
                ->lockForUpdate()
                ->get();

            foreach ($abiertas as $previa) {
                if ($previa->persona_id === (int) $datos['persona_id']) {
                    throw ValidationException::withMessages([
                        'persona_id' => 'La persona ya es la máxima autoridad vigente del organismo.',
                    ]);
                }
                if ($previa->fecha_inicio && $previa->fecha_inicio->gte($inicio)) {
                    throw ValidationException::withMessages([
                        'fecha_inicio' => 'La fecha de inicio debe ser posterior al inicio de la autoridad vigente ('.$previa->fecha_inicio->format('d/m/Y').').',
                    ]);
                }

                $anterior = $previa->toArray();
                $previa->update([
                    'fecha_fin' => $cese,
                    'observaciones' => trim(($previa->observaciones ?? '')."\nCese por nueva designación"),
                ]);
                AuditService::log('UPDATE', 'AUTORIDADES_ORGANISMO', $previa->id, $anterior, $previa->toArray(), 'Cese por nueva designación');
            }

            $autoridad = AutoridadOrganismo::create([
                'ministerio_id' => $ministerio->id,
                'persona_id' => $datos['persona_id'],
                'cargo' => $datos['cargo'] ?? null,
                'fecha_inicio' => $datos['fecha_inicio'] ?? null,
                'fecha_fin' => null,
                'acto_administrativo' => $datos['acto_administrativo'] ?? null,
                'observaciones' => $datos['observaciones'] ?? null,
            ]);
            AuditService::log('CREATE', 'AUTORIDADES_ORGANISMO', $autoridad->id, null, $autoridad->toArray(), 'Designación de máxima autoridad');

            return $autoridad;
        });
    }
}
