<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Historial de una intervención. Solo inserción: un movimiento registrado no se modifica ni se borra.
 * Los tipos están fijos en el código porque los genera el propio sistema (D-30).
 */
class ExpedienteMovimiento extends Model
{
    public const TIPOS = [
        'INGRESO' => 'Ingreso al área',
        'ASIGNACION' => 'Asignación de responsable',
        'CAMBIO_ESTADO' => 'Cambio de estado',
        'ENVIO_REVISION' => 'Envío a revisión',
        'FIRMA' => 'Firma',
        'EGRESO' => 'Egreso del área',
        'REAPERTURA' => 'Reapertura',
        'NOTA' => 'Nota / intervención',
        'ACTUALIZACION' => 'Actualización de datos',
        'CORRECCION' => 'Corrección de fechas',
    ];

    public const UPDATED_AT = null;

    protected $table = 'expediente_movimientos';

    protected $fillable = [
        'intervencion_id',
        'fecha',
        'tipo',
        'estado_anterior_id',
        'estado_nuevo_id',
        'user_id',
        'descripcion',
        'referencia_documento',
        'fecha_proximo_paso',
        'importado',
    ];

    protected $casts = [
        'fecha' => 'date:Y-m-d',
        'fecha_proximo_paso' => 'date:Y-m-d',
        'importado' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los movimientos de expedientes no se modifican.'));
        static::deleting(fn () => throw new LogicException('Los movimientos de expedientes no se eliminan.'));
    }

    public function intervencion(): BelongsTo
    {
        return $this->belongsTo(ExpedienteIntervencion::class, 'intervencion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function estadoAnterior(): BelongsTo
    {
        return $this->belongsTo(ExpedienteEstado::class, 'estado_anterior_id');
    }

    public function estadoNuevo(): BelongsTo
    {
        return $this->belongsTo(ExpedienteEstado::class, 'estado_nuevo_id');
    }
}
