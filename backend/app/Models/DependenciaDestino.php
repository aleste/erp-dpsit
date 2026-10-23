<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Dependencia a la que se remite un expediente al egresar (columna «Remitido a» de la planilla)
 */
class DependenciaDestino extends Model
{
    protected $table = 'dependencias_destino';

    protected $fillable = ['nombre', 'activo'];

    protected $casts = ['activo' => 'boolean'];
}
