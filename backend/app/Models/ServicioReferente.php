<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicioReferente extends Model
{
    use HasFactory;

    protected $table = 'servicio_referentes';

    protected $fillable = [
        'servicio_area_id',
        'persona_id',
        'tipo_referente',
        'es_principal',
        'notificaciones_activas',
    ];

    protected $casts = [
        'es_principal' => 'boolean',
        'notificaciones_activas' => 'boolean',
    ];

    public function servicioArea(): BelongsTo
    {
        return $this->belongsTo(ServicioArea::class, 'servicio_area_id');
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }
}
