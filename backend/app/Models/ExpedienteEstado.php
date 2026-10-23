<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Estado configurable de una intervención (D-30). Los estados finales representan el egreso del área.
 */
class ExpedienteEstado extends Model
{
    protected $table = 'expediente_estados';

    protected $fillable = ['nombre', 'orden', 'color', 'es_final', 'requiere_documento', 'exime_documento', 'activo'];

    protected $casts = [
        'orden' => 'integer',
        'es_final' => 'boolean',
        'requiere_documento' => 'boolean',
        'exime_documento' => 'boolean',
        'activo' => 'boolean',
    ];
}
