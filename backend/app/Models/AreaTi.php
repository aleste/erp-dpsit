<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AreaTi extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'areas_ti';

    protected $fillable = [
        'ministerio_id',
        'codigo',
        'nombre',
        'nivel_jerarquico',
        'es_homologada',
        'resolucion_homologacion',
        'domicilio_fisico',
        'telefono_contacto',
        'email_institucional',
        'activo',
    ];

    protected $casts = [
        'es_homologada' => 'boolean',
        'activo' => 'boolean',
    ];

    public function ministerio(): BelongsTo
    {
        return $this->belongsTo(Ministerio::class, 'ministerio_id');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionRol::class, 'area_ti_id');
    }

    public function asignacionesVigentes(): HasMany
    {
        return $this->hasMany(AsignacionRol::class, 'area_ti_id')
            ->where('activo', true)
            ->where(function ($query) {
                $query->whereNull('fecha_fin')
                    ->orWhere('fecha_fin', '>=', now()->toDateString());
            });
    }

    public function maximaAutoridadVigente(): HasOne
    {
        return $this->hasOne(AsignacionRol::class, 'area_ti_id')
            ->where('es_maxima_autoridad', true)
            ->where('activo', true)
            ->where(function ($query) {
                $query->whereNull('fecha_fin')
                    ->orWhere('fecha_fin', '>=', now()->toDateString());
            })
            ->latest('fecha_inicio');
    }

    public function servicios(): HasMany
    {
        return $this->hasMany(ServicioArea::class, 'area_ti_id');
    }

    public function documentos(): MorphMany
    {
        return $this->morphMany(DocumentoAdjunto::class, 'documentable');
    }
}
