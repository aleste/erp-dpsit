<?php

namespace App\Services;

use App\Models\AsignacionRol;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Máxima autoridad de un área TI con las mismas reglas que los organismos (D-32, resuelve DEF-09):
 * al designar una autoridad sin fecha de fin se cierran todas las autoridades abiertas o con cese
 * futuro del área, con cese el día anterior al inicio de la nueva, de modo que nunca quedan dos
 * vigentes. Una asignación con fecha de fin (histórica o temporal) no afecta a las demás.
 */
class AutoridadAreaService
{
    /**
     * Crea la asignación de máxima autoridad cerrando las anteriores
     */
    public function designar(array $datos): AsignacionRol
    {
        return DB::transaction(function () use ($datos) {
            $datos['es_maxima_autoridad'] = true;
            $this->cerrarPrevias($datos);

            $asignacion = AsignacionRol::create($datos);
            AuditService::log('CREATE', 'ASIGNACIONES_ROL', $asignacion->id, null, $asignacion->toArray(), 'Designación de máxima autoridad del área');

            return $asignacion;
        });
    }

    /**
     * Cierra las autoridades del área que seguirían vigentes junto con la nueva designación.
     * $exceptoId excluye la propia asignación cuando se edita una existente.
     */
    public function cerrarPrevias(array $datos, ?int $exceptoId = null): void
    {
        if (! empty($datos['fecha_fin'])) {
            return;
        }

        $inicio = Carbon::parse($datos['fecha_inicio'])->startOfDay();
        $cese = $inicio->copy()->subDay()->toDateString();

        $abiertas = AsignacionRol::where('area_ti_id', $datos['area_ti_id'])
            ->where('es_maxima_autoridad', true)
            ->where('activo', true)
            ->when($exceptoId, fn ($q) => $q->where('id', '!=', $exceptoId))
            ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $inicio->toDateString()))
            ->lockForUpdate()
            ->get();

        foreach ($abiertas as $previa) {
            if ($previa->persona_id === (int) $datos['persona_id']) {
                throw ValidationException::withMessages([
                    'persona_id' => 'La persona ya es la máxima autoridad vigente del área.',
                ]);
            }

            if ($previa->fecha_inicio->gte($inicio)) {
                throw ValidationException::withMessages([
                    'fecha_inicio' => 'La fecha de inicio debe ser posterior al inicio de la autoridad vigente ('.$previa->fecha_inicio->format('d/m/Y').').',
                ]);
            }

            $anterior = $previa->toArray();
            $previa->update([
                'fecha_fin' => $cese,
                'observaciones' => trim(($previa->observaciones ?? '')."\nCese por nueva designación de autoridad"),
            ]);
            AuditService::log('UPDATE', 'ASIGNACIONES_ROL', $previa->id, $anterior, $previa->toArray(), 'Cese por nueva designación de autoridad');
        }
    }
}
