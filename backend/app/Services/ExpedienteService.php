<?php

namespace App\Services;

use App\Models\DependenciaDestino;
use App\Models\Expediente;
use App\Models\ExpedienteEstado;
use App\Models\ExpedienteIntervencion;
use App\Models\ExpedienteMovimiento;
use App\Models\User;
use App\Models\UserModulo;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reglas del circuito de expedientes. Toda acción sobre una intervención actualiza su situación actual
 * y SIEMPRE inserta un movimiento en la misma transacción; los movimientos nunca se modifican (D-30).
 */
class ExpedienteService
{
    /**
     * Ingreso al área: crea el expediente si no existe y abre una nueva intervención (1ra, 2da, ...)
     */
    public function registrarIngreso(array $datos, User $actor): ExpedienteIntervencion
    {
        $atributos = Expediente::atributosDeNumero($datos['numero']);

        return DB::transaction(function () use ($datos, $atributos, $actor) {
            $expediente = Expediente::withTrashed()
                ->where(fn ($q) => $q->where('numero', $atributos['numero'])->orWhere('numero_busqueda', $atributos['numero_busqueda']))
                ->lockForUpdate()
                ->first();

            // Un expediente dado de baja por error que vuelve a ingresar se restaura (sus intervenciones anuladas siguen anuladas)
            if ($expediente?->trashed()) {
                $expediente->restore();
                AuditService::log('UPDATE', 'EXPEDIENTES', $expediente->id, null, null, "Expediente {$expediente->numero} restaurado al registrar un nuevo ingreso");
            }

            if ($expediente) {
                $abierta = $expediente->intervenciones()->where('en_area', true)->first();
                if ($abierta) {
                    $this->error('numero', "El expediente ya está en el área (intervención {$abierta->numero_intervencion}). Registre los movimientos sobre esa intervención.");
                }
                if (empty($datos['descripcion'])) {
                    $this->error('descripcion', 'Indique el motivo del reingreso del expediente.');
                }
                // En un reingreso se completan los datos del expediente que falten, sin pisar los existentes
                $expediente->fill(array_filter([
                    'caratula' => $expediente->caratula ? null : ($datos['caratula'] ?? null),
                    'ministerio_id' => $expediente->ministerio_id ? null : ($datos['ministerio_id'] ?? null),
                ]))->save();
            } else {
                $expediente = Expediente::create($atributos + [
                    'caratula' => $datos['caratula'] ?? null,
                    'ministerio_id' => $datos['ministerio_id'] ?? null,
                ]);
            }

            $estado = isset($datos['estado_id'])
                ? $this->estadoNoFinal($datos['estado_id'])
                : $this->estadoInicial();

            if (! empty($datos['responsable_id'])) {
                $this->validarIntegrante($datos['responsable_id'], 'responsable_id');
            }

            $intervencion = $expediente->intervenciones()->create([
                'numero_intervencion' => (int) $expediente->intervenciones()->withTrashed()->max('numero_intervencion') + 1,
                'fecha_ingreso' => $datos['fecha_ingreso'],
                'en_area' => true,
                'estado_id' => $estado->id,
                'tipo_requerimiento_id' => $datos['tipo_requerimiento_id'] ?? null,
                'descripcion' => $datos['descripcion'] ?? null,
                'responsable_id' => $datos['responsable_id'] ?? null,
                'prioridad' => $datos['prioridad'] ?? 'NORMAL',
                'proxima_accion' => $datos['proxima_accion'] ?? null,
                'fecha_proxima_accion' => $datos['fecha_proxima_accion'] ?? null,
                'fecha_vencimiento' => $datos['fecha_vencimiento'] ?? null,
                'observaciones' => $datos['observaciones'] ?? null,
            ]);

            $this->sincronizarColaboradores($intervencion, $datos['colaboradores'] ?? []);

            $this->movimiento($intervencion, 'INGRESO', $actor, [
                'fecha' => $datos['fecha_ingreso'],
                'estado_nuevo_id' => $estado->id,
                'descripcion' => $intervencion->numero_intervencion > 1
                    ? "Reingreso ({$intervencion->numero_intervencion}ª intervención): {$datos['descripcion']}"
                    : ($datos['descripcion'] ?? 'Ingreso al área'),
            ]);

            if ($intervencion->responsable_id) {
                $this->movimiento($intervencion, 'ASIGNACION', $actor, [
                    'fecha' => $datos['fecha_ingreso'],
                    'descripcion' => 'Responsable: '.$intervencion->responsable->name,
                ]);
            }

            AuditService::log('CREATE', 'EXPEDIENTES', $intervencion->id, null, $intervencion->toArray(), "Ingreso de {$expediente->numero}");

            return $intervencion;
        });
    }

    /**
     * Datos editables del expediente y de la intervención (no fechas del circuito ni estado)
     */
    public function actualizarDatos(ExpedienteIntervencion $intervencion, array $datos, User $actor): ExpedienteIntervencion
    {
        return DB::transaction(function () use ($intervencion, $datos, $actor) {
            $expediente = $intervencion->expediente;
            $cambios = [];

            foreach (['caratula', 'ministerio_id'] as $campo) {
                if (array_key_exists($campo, $datos) && $expediente->{$campo} != $datos[$campo]) {
                    $cambios[] = $this->describirCambio($campo, $expediente->{$campo}, $datos[$campo]);
                    $expediente->{$campo} = $datos[$campo];
                }
            }
            $expediente->save();

            $campos = ['tipo_requerimiento_id', 'descripcion', 'prioridad', 'proxima_accion', 'fecha_proxima_accion',
                'fecha_vencimiento', 'etb', 'con_acto_administrativo', 'observaciones', 'documento_respuesta'];
            $anterior = $intervencion->toArray();
            foreach ($campos as $campo) {
                if (! array_key_exists($campo, $datos)) {
                    continue;
                }
                $actual = $intervencion->{$campo} instanceof Carbon ? $intervencion->{$campo}->toDateString() : $intervencion->{$campo};
                if ($actual != $datos[$campo]) {
                    $cambios[] = $this->describirCambio($campo, $actual, $datos[$campo]);
                    $intervencion->{$campo} = $datos[$campo];
                }
            }

            if (! $cambios) {
                return $intervencion;
            }

            $intervencion->save();
            $this->movimiento($intervencion, 'ACTUALIZACION', $actor, [
                'descripcion' => implode('; ', $cambios),
                'fecha_proximo_paso' => $datos['fecha_proxima_accion'] ?? null,
            ]);
            AuditService::log('UPDATE', 'EXPEDIENTES', $intervencion->id, $anterior, $intervencion->toArray());

            return $intervencion;
        });
    }

    /**
     * Asigna responsable y colaboradores. Tomar uno sin responsable puede cualquier operador;
     * reasignar uno que ya tiene responsable (a otra persona) requiere rol SUPERVISOR.
     */
    public function asignar(ExpedienteIntervencion $intervencion, ?int $responsableId, array $colaboradores, User $actor, ?string $descripcion = null): ExpedienteIntervencion
    {
        $this->exigirAbierta($intervencion);

        if ($intervencion->responsable_id && $intervencion->responsable_id !== $responsableId
            && ! $actor->puedeEnModulo(UserModulo::EXPEDIENTES, 'SUPERVISOR')) {
            throw new AuthorizationException('Solo un supervisor puede reasignar un expediente que ya tiene responsable.');
        }

        if ($responsableId) {
            $this->validarIntegrante($responsableId, 'responsable_id');
        }

        return DB::transaction(function () use ($intervencion, $responsableId, $colaboradores, $actor, $descripcion) {
            $anterior = $intervencion->responsable?->name ?? 'sin responsable';
            $intervencion->update(['responsable_id' => $responsableId]);
            $this->sincronizarColaboradores($intervencion, $colaboradores);

            $nuevo = $intervencion->fresh('responsable')->responsable?->name ?? 'sin responsable';
            $nombresColaboradores = $intervencion->colaboradores()->pluck('name')->implode(', ');
            $this->movimiento($intervencion, 'ASIGNACION', $actor, [
                'descripcion' => trim("Responsable: {$anterior} → {$nuevo}".($nombresColaboradores ? ". Colaboran: {$nombresColaboradores}" : '').($descripcion ? ". {$descripcion}" : '')),
            ]);
            AuditService::log('UPDATE', 'EXPEDIENTES', $intervencion->id, null, ['responsable_id' => $responsableId], 'Asignación de responsable');

            return $intervencion;
        });
    }

    public function cambiarEstado(ExpedienteIntervencion $intervencion, int $estadoId, User $actor, ?string $descripcion = null, ?string $fecha = null): ExpedienteIntervencion
    {
        $this->exigirAbierta($intervencion);
        $estado = $this->estadoNoFinal($estadoId);

        if ($estado->id === $intervencion->estado_id) {
            $this->error('estado_id', 'El expediente ya se encuentra en ese estado.');
        }

        return DB::transaction(function () use ($intervencion, $estado, $actor, $descripcion, $fecha) {
            $anteriorId = $intervencion->estado_id;
            $intervencion->update(['estado_id' => $estado->id]);
            $this->movimiento($intervencion, 'CAMBIO_ESTADO', $actor, [
                'fecha' => $fecha,
                'estado_anterior_id' => $anteriorId,
                'estado_nuevo_id' => $estado->id,
                'descripcion' => $descripcion,
            ]);
            AuditService::log('UPDATE', 'EXPEDIENTES', $intervencion->id, ['estado_id' => $anteriorId], ['estado_id' => $estado->id], 'Cambio de estado');

            return $intervencion;
        });
    }

    /**
     * Envío de la respuesta a revisión (columnas «A revisión» y «Envíado a» de la planilla)
     */
    public function enviarRevision(ExpedienteIntervencion $intervencion, array $datos, User $actor): ExpedienteIntervencion
    {
        $this->exigirAbierta($intervencion);
        $this->validarIntegrante($datos['revisor_id'], 'revisor_id');
        $this->validarFechaEnIntervencion($intervencion, $datos['fecha'], 'fecha');
        $estado = isset($datos['estado_id']) ? $this->estadoNoFinal($datos['estado_id']) : null;

        return DB::transaction(function () use ($intervencion, $datos, $actor, $estado) {
            $anteriorId = $intervencion->estado_id;
            $intervencion->update(array_filter([
                'revisor_id' => $datos['revisor_id'],
                'fecha_envio_revision' => $datos['fecha'],
                'estado_id' => $estado?->id,
                'documento_respuesta' => $datos['documento_respuesta'] ?? null,
            ]));
            $revisor = User::find($datos['revisor_id']);
            $this->movimiento($intervencion, 'ENVIO_REVISION', $actor, [
                'fecha' => $datos['fecha'],
                'estado_anterior_id' => $estado ? $anteriorId : null,
                'estado_nuevo_id' => $estado?->id,
                'descripcion' => trim('Enviado a revisión de '.$revisor->name.'. '.($datos['descripcion'] ?? '')),
                'referencia_documento' => $datos['documento_respuesta'] ?? null,
            ]);
            AuditService::log('UPDATE', 'EXPEDIENTES', $intervencion->id, null, ['revisor_id' => $datos['revisor_id']], 'Envío a revisión');

            return $intervencion;
        });
    }

    public function registrarFirma(ExpedienteIntervencion $intervencion, array $datos, User $actor): ExpedienteIntervencion
    {
        $this->exigirAbierta($intervencion);
        $this->validarFechaEnIntervencion($intervencion, $datos['fecha'], 'fecha');

        return DB::transaction(function () use ($intervencion, $datos, $actor) {
            $intervencion->update(array_filter([
                'fecha_firma' => $datos['fecha'],
                'documento_respuesta' => $datos['documento_respuesta'] ?? null,
            ]));
            $this->movimiento($intervencion, 'FIRMA', $actor, [
                'fecha' => $datos['fecha'],
                'descripcion' => $datos['descripcion'] ?? 'Documento de respuesta firmado',
                'referencia_documento' => $datos['documento_respuesta'] ?? $intervencion->documento_respuesta,
            ]);
            AuditService::log('UPDATE', 'EXPEDIENTES', $intervencion->id, null, ['fecha_firma' => $datos['fecha']], 'Firma registrada');

            return $intervencion;
        });
    }

    /**
     * Egreso del área (finalización de la intervención): estado final, fecha, destino y documento de respuesta
     */
    public function egresar(ExpedienteIntervencion $intervencion, array $datos, User $actor): ExpedienteIntervencion
    {
        $this->exigirAbierta($intervencion);
        $this->validarFechaEnIntervencion($intervencion, $datos['fecha_egreso'], 'fecha_egreso');

        $final = ExpedienteEstado::where('id', $datos['estado_id'])->where('es_final', true)->where('activo', true)->first();
        if (! $final) {
            $this->error('estado_id', 'Seleccione un estado de egreso válido.');
        }

        $documento = $datos['documento_respuesta'] ?? $intervencion->documento_respuesta;
        if ($final->requiere_documento && ! $intervencion->estado?->exime_documento && empty($documento)) {
            $this->error('documento_respuesta', 'Indique el documento de respuesta (p. ej. el número de GEDO) para registrar la salida.');
        }

        return DB::transaction(function () use ($intervencion, $datos, $actor, $final, $documento) {
            $destino = $this->resolverDestino($datos);
            $anteriorId = $intervencion->estado_id;
            $anterior = $intervencion->toArray();

            $intervencion->update([
                'en_area' => false,
                'fecha_egreso' => $datos['fecha_egreso'],
                'estado_id' => $final->id,
                'dependencia_destino_id' => $destino->id,
                'documento_respuesta' => $documento,
                'fecha_proxima_accion' => null,
                'proxima_accion' => null,
            ]);
            $this->movimiento($intervencion, 'EGRESO', $actor, [
                'fecha' => $datos['fecha_egreso'],
                'estado_anterior_id' => $anteriorId,
                'estado_nuevo_id' => $final->id,
                'descripcion' => trim("Remitido a {$destino->nombre}. ".($datos['descripcion'] ?? '')),
                'referencia_documento' => $documento,
            ]);
            AuditService::log('UPDATE', 'EXPEDIENTES', $intervencion->id, $anterior, $intervencion->toArray(), 'Egreso del área');

            return $intervencion;
        });
    }

    public function agregarNota(ExpedienteIntervencion $intervencion, array $datos, User $actor): ExpedienteMovimiento
    {
        return DB::transaction(function () use ($intervencion, $datos, $actor) {
            // Una nota con próximo paso actualiza la próxima acción si el expediente sigue en el área
            if (! empty($datos['fecha_proximo_paso']) && $intervencion->en_area) {
                $intervencion->update([
                    'fecha_proxima_accion' => $datos['fecha_proximo_paso'],
                    'proxima_accion' => mb_substr($datos['descripcion'], 0, 255),
                ]);
            }

            return $this->movimiento($intervencion, 'NOTA', $actor, [
                'fecha' => $datos['fecha'] ?? null,
                'descripcion' => $datos['descripcion'],
                'referencia_documento' => $datos['referencia_documento'] ?? null,
                'fecha_proximo_paso' => $datos['fecha_proximo_paso'] ?? null,
            ]);
        });
    }

    /**
     * Reapertura de una intervención egresada por error (SUPERVISOR). Un nuevo pase por el área NO es
     * una reapertura: es un reingreso (nueva intervención).
     */
    public function reabrir(ExpedienteIntervencion $intervencion, array $datos, User $actor): ExpedienteIntervencion
    {
        if ($intervencion->en_area) {
            $this->error('intervencion', 'La intervención ya está abierta.');
        }

        $ultima = $intervencion->expediente->intervenciones()->max('numero_intervencion');
        if ($intervencion->numero_intervencion !== (int) $ultima) {
            $this->error('intervencion', 'Solo puede reabrirse la última intervención del expediente.');
        }

        $estado = $this->estadoNoFinal($datos['estado_id']);

        return DB::transaction(function () use ($intervencion, $datos, $actor, $estado) {
            $anterior = $intervencion->toArray();
            $anteriorId = $intervencion->estado_id;
            $intervencion->update(['en_area' => true, 'fecha_egreso' => null, 'estado_id' => $estado->id]);
            $this->movimiento($intervencion, 'REAPERTURA', $actor, [
                'estado_anterior_id' => $anteriorId,
                'estado_nuevo_id' => $estado->id,
                'descripcion' => 'Motivo: '.$datos['motivo'].'. Egreso anulado: '.($anterior['fecha_egreso'] ?? 'sin fecha'),
            ]);
            AuditService::log('UPDATE', 'EXPEDIENTES', $intervencion->id, $anterior, $intervencion->toArray(), 'Reapertura: '.$datos['motivo']);

            return $intervencion;
        });
    }

    /**
     * Corrección de fechas del circuito (SUPERVISOR), siempre con motivo
     */
    public function corregirFechas(ExpedienteIntervencion $intervencion, array $datos, User $actor): ExpedienteIntervencion
    {
        $campos = ['fecha_ingreso', 'fecha_egreso', 'fecha_envio_revision', 'fecha_firma'];
        $nuevos = array_intersect_key($datos, array_flip($campos));

        if (array_key_exists('fecha_egreso', $nuevos) && $intervencion->en_area) {
            $this->error('fecha_egreso', 'La fecha de egreso se registra al egresar el expediente.');
        }

        $ingreso = Carbon::parse($nuevos['fecha_ingreso'] ?? $intervencion->fecha_ingreso);
        foreach (['fecha_egreso', 'fecha_envio_revision', 'fecha_firma'] as $campo) {
            $valor = array_key_exists($campo, $nuevos) ? $nuevos[$campo] : $intervencion->{$campo};
            if ($valor && Carbon::parse($valor)->lt($ingreso)) {
                $this->error($campo, 'La fecha no puede ser anterior al ingreso.');
            }
        }

        return DB::transaction(function () use ($intervencion, $nuevos, $datos, $actor) {
            $cambios = [];
            foreach ($nuevos as $campo => $valor) {
                $actual = $intervencion->{$campo}?->toDateString();
                if ($actual !== $valor) {
                    $cambios[] = $this->describirCambio($campo, $actual, $valor);
                    $intervencion->{$campo} = $valor;
                }
            }
            if (! $cambios) {
                $this->error('fechas', 'No hay cambios de fechas para registrar.');
            }

            $intervencion->save();
            $this->movimiento($intervencion, 'CORRECCION', $actor, [
                'descripcion' => implode('; ', $cambios).'. Motivo: '.$datos['motivo'],
            ]);
            AuditService::log('UPDATE', 'EXPEDIENTES', $intervencion->id, null, $nuevos, 'Corrección de fechas: '.$datos['motivo']);

            return $intervencion;
        });
    }

    public function estadoInicial(): ExpedienteEstado
    {
        return ExpedienteEstado::where('es_final', false)->where('activo', true)->orderBy('orden')->orderBy('id')->firstOrFail();
    }

    private function estadoNoFinal(int $id): ExpedienteEstado
    {
        $estado = ExpedienteEstado::where('id', $id)->where('activo', true)->first();
        if (! $estado || $estado->es_final) {
            $this->error('estado_id', 'Seleccione un estado de trabajo válido (los estados de salida se registran con el egreso).');
        }

        return $estado;
    }

    private function movimiento(ExpedienteIntervencion $intervencion, string $tipo, ?User $actor, array $datos): ExpedienteMovimiento
    {
        return $intervencion->movimientos()->create(array_merge([
            'tipo' => $tipo,
            'user_id' => $actor?->id,
        ], $datos, [
            'fecha' => $datos['fecha'] ?? now()->toDateString(),
        ]));
    }

    private function exigirAbierta(ExpedienteIntervencion $intervencion): void
    {
        if (! $intervencion->en_area) {
            $this->error('intervencion', 'La intervención ya egresó del área. Para un nuevo pase registre un reingreso.');
        }
    }

    private function validarFechaEnIntervencion(ExpedienteIntervencion $intervencion, string $fecha, string $campo): void
    {
        if (Carbon::parse($fecha)->lt($intervencion->fecha_ingreso)) {
            $this->error($campo, 'La fecha no puede ser anterior al ingreso del expediente.');
        }
        if (Carbon::parse($fecha)->gt(now()->endOfDay())) {
            $this->error($campo, 'La fecha no puede ser futura.');
        }
    }

    // Responsables y revisores deben ser usuarios activos con acceso operativo al módulo
    private function validarIntegrante(int $userId, string $campo): void
    {
        $user = User::with('modulos')->find($userId);
        if (! $user || ! $user->puedeEnModulo(UserModulo::EXPEDIENTES, 'OPERADOR')) {
            $this->error($campo, 'El usuario seleccionado no es integrante activo del módulo de expedientes.');
        }
    }

    private function sincronizarColaboradores(ExpedienteIntervencion $intervencion, array $colaboradores): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $colaboradores),
            fn ($id) => $id && $id !== $intervencion->responsable_id)));
        foreach ($ids as $id) {
            $this->validarIntegrante($id, 'colaboradores');
        }
        $intervencion->colaboradores()->sync($ids);
    }

    /**
     * Destino existente por id, o alta por nombre (normalizado) si no existe
     */
    private function resolverDestino(array $datos): DependenciaDestino
    {
        if (! empty($datos['dependencia_destino_id'])) {
            return DependenciaDestino::findOrFail($datos['dependencia_destino_id']);
        }

        $nombre = trim(preg_replace('/\s+/', ' ', $datos['dependencia_destino_nombre'] ?? ''));
        if ($nombre === '') {
            $this->error('dependencia_destino_id', 'Indique a qué dependencia se remite el expediente.');
        }

        return DependenciaDestino::whereRaw('lower(nombre) = ?', [mb_strtolower($nombre)])->first()
            ?? DependenciaDestino::create(['nombre' => $nombre]);
    }

    private function describirCambio(string $campo, $antes, $despues): string
    {
        $fmt = fn ($v) => ($v === null || $v === '') ? '—' : (is_string($v) ? mb_strimwidth($v, 0, 60, '…') : $v);

        return "{$campo}: {$fmt($antes)} → {$fmt($despues)}";
    }

    private function error(string $campo, string $mensaje): never
    {
        throw ValidationException::withMessages([$campo => [$mensaje]]);
    }
}
