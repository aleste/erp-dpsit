<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ministerio extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ministerios';

    protected $fillable = [
        'codigo',
        'nombre',
        'sigla',
        'orden_prioridad',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden_prioridad' => 'integer',
    ];

    public function areasTi(): HasMany
    {
        return $this->hasMany(AreaTi::class, 'ministerio_id');
    }

    public function autoridades(): HasMany
    {
        return $this->hasMany(AutoridadOrganismo::class, 'ministerio_id');
    }

    public function autoridadVigente(): HasOne
    {
        return $this->hasOne(AutoridadOrganismo::class, 'ministerio_id')
            ->vigente()
            ->latest('fecha_inicio');
    }

    public function asignacionesRol(): HasManyThrough
    {
        return $this->hasManyThrough(
            AsignacionRol::class,
            AreaTi::class,
            'ministerio_id',
            'area_ti_id',
            'id',
            'id'
        );
    }
}
