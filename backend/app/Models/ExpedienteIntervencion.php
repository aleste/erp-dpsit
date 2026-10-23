<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un pase del expediente por el área: desde el ingreso hasta el egreso (una fila de la planilla)
 */
class ExpedienteIntervencion extends Model
{
    use SoftDeletes;

    public const PRIORIDADES = ['NORMAL', 'ALTA'];

    public const ACTO_ADMINISTRATIVO = ['SI', 'NO_CORRESPONDE', 'PENDIENTE'];

    protected $table = 'expediente_intervenciones';

    protected $fillable = [
        'expediente_id',
        'numero_intervencion',
        'fecha_ingreso',
        'fecha_egreso',
        'en_area',
        'estado_id',
        'tipo_requerimiento_id',
        'descripcion',
        'responsable_id',
        'revisor_id',
        'fecha_envio_revision',
        'fecha_firma',
        'dependencia_destino_id',
        'documento_respuesta',
        'prioridad',
        'proxima_accion',
        'fecha_proxima_accion',
        'fecha_vencimiento',
        'etb',
        'con_acto_administrativo',
        'observaciones',
        'origen',
        'origen_referencia',
    ];

    protected $casts = [
        'numero_intervencion' => 'integer',
        'en_area' => 'boolean',
        'fecha_ingreso' => 'date:Y-m-d',
        'fecha_egreso' => 'date:Y-m-d',
        'fecha_envio_revision' => 'date:Y-m-d',
        'fecha_firma' => 'date:Y-m-d',
        'fecha_proxima_accion' => 'date:Y-m-d',
        'fecha_vencimiento' => 'date:Y-m-d',
    ];

    protected $appends = ['dias_en_area'];

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class, 'expediente_id');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(ExpedienteEstado::class, 'estado_id');
    }

    public function tipoRequerimiento(): BelongsTo
    {
        return $this->belongsTo(ExpedienteTipoRequerimiento::class, 'tipo_requerimiento_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisor_id');
    }

    public function dependenciaDestino(): BelongsTo
    {
        return $this->belongsTo(DependenciaDestino::class, 'dependencia_destino_id');
    }

    public function colaboradores(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'expediente_intervencion_colaboradores', 'intervencion_id', 'user_id')
            ->withTimestamps();
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(ExpedienteMovimiento::class, 'intervencion_id');
    }

    // Antigüedad en el área: hasta hoy si sigue en el área, hasta el egreso si ya salió
    public function getDiasEnAreaAttribute(): ?int
    {
        if (! $this->fecha_ingreso || (! $this->en_area && ! $this->fecha_egreso)) {
            return null; // Egresó sin fecha registrada (dato importado)
        }

        $hasta = $this->en_area ? now()->startOfDay() : $this->fecha_egreso;

        return (int) $this->fecha_ingreso->diffInDays($hasta);
    }

    public function scopeEnArea(Builder $query): Builder
    {
        return $query->where('en_area', true);
    }

    public function scopeAccionVencida(Builder $query): Builder
    {
        return $query->where('en_area', true)->whereDate('fecha_proxima_accion', '<', now()->toDateString());
    }
}
