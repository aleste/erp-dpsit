<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServicioArea extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'servicios_areas';

    protected $fillable = [
        'area_ti_id',
        'catalogo_servicio_id',
        'codigo_instancia',
        'estado_servicio',
        'sla_nivel',
        'fecha_alta',
        'fecha_baja',
        'observaciones',
    ];

    protected $casts = [
        'fecha_alta' => 'date:Y-m-d',
        'fecha_baja' => 'date:Y-m-d',
    ];

    public function areaTi(): BelongsTo
    {
        return $this->belongsTo(AreaTi::class, 'area_ti_id');
    }

    public function catalogoServicio(): BelongsTo
    {
        return $this->belongsTo(CatalogoServicio::class, 'catalogo_servicio_id');
    }

    public function referentes(): HasMany
    {
        return $this->hasMany(ServicioReferente::class, 'servicio_area_id');
    }

    public function documentos(): MorphMany
    {
        return $this->morphMany(DocumentoAdjunto::class, 'documentable');
    }
}
