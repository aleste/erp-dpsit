<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AsignacionRol extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'asignaciones_rol';

    protected $fillable = [
        'persona_id',
        'area_ti_id',
        'tipo_rol_id',
        'fecha_inicio',
        'fecha_fin',
        'es_maxima_autoridad',
        'acto_administrativo',
        'observaciones',
        'activo',
    ];

    protected $casts = [
        'fecha_inicio' => 'date:Y-m-d',
        'fecha_fin' => 'date:Y-m-d',
        'es_maxima_autoridad' => 'boolean',
        'activo' => 'boolean',
    ];

    protected $appends = ['estado_vigencia'];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }

    public function areaTi(): BelongsTo
    {
        return $this->belongsTo(AreaTi::class, 'area_ti_id');
    }

    public function tipoRol(): BelongsTo
    {
        return $this->belongsTo(TipoRol::class, 'tipo_rol_id');
    }

    public function documentos(): MorphMany
    {
        return $this->morphMany(DocumentoAdjunto::class, 'documentable');
    }

    // Accessor para saber si la asignación está vigente
    public function getEstadoVigenciaAttribute(): string
    {
        if (!$this->activo) {
            return 'INACTIVO';
        }

        $hoy = now()->toDateString();
        if ($this->fecha_fin !== null && $this->fecha_fin->format('Y-m-d') < $hoy) {
            return 'HISTORICO';
        }

        if ($this->fecha_inicio->format('Y-m-d') > $hoy) {
            return 'FUTURO';
        }

        return 'VIGENTE';
    }

    // Scopes de Negocio
    public function scopeVigente(Builder $query): Builder
    {
        $hoy = now()->toDateString();
        return $query->where('activo', true)
            ->where('fecha_inicio', '<=', $hoy)
            ->where(function ($q) use ($hoy) {
                $q->whereNull('fecha_fin')
                  ->orWhere('fecha_fin', '>=', $hoy);
            });
    }

    public function scopeHistorico(Builder $query): Builder
    {
        $hoy = now()->toDateString();
        return $query->where(function ($q) use ($hoy) {
            $q->where('activo', false)
              ->orWhere('fecha_fin', '<', $hoy);
        });
    }

    public function scopeMaximaAutoridad(Builder $query): Builder
    {
        return $query->where('es_maxima_autoridad', true);
    }

    public function scopePorMinisterio(Builder $query, int|string $ministerioId): Builder
    {
        return $query->whereHas('areaTi', function ($q) use ($ministerioId) {
            $q->where('ministerio_id', $ministerioId);
        });
    }

    public function scopePorRol(Builder $query, int|string $tipoRolId): Builder
    {
        return $query->where('tipo_rol_id', $tipoRolId);
    }
}
