<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoRol extends Model
{
    use HasFactory;

    protected $table = 'tipos_rol';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'nivel_responsabilidad',
        'es_maxima_autoridad_default',
        'activo',
    ];

    protected $casts = [
        'es_maxima_autoridad_default' => 'boolean',
        'activo' => 'boolean',
    ];

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionRol::class, 'tipo_rol_id');
    }
}
