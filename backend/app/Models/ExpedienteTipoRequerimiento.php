<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpedienteTipoRequerimiento extends Model
{
    public const GRUPOS = ['PERSONAL', 'BIENES_SERVICIOS', 'NORMATIVA', 'OTROS'];

    protected $table = 'expediente_tipos_requerimiento';

    protected $fillable = ['nombre', 'grupo', 'activo'];

    protected $casts = ['activo' => 'boolean'];
}
