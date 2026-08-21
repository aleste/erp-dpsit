<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoServicio extends Model
{
    use HasFactory;

    protected $table = 'catalogo_servicios';

    protected $fillable = [
        'codigo',
        'nombre',
        'categoria',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function serviciosAreas(): HasMany
    {
        return $this->hasMany(ServicioArea::class, 'catalogo_servicio_id');
    }
}
