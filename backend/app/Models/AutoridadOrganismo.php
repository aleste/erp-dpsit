<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AutoridadOrganismo extends Model
{
    use SoftDeletes;

    protected $table = 'autoridades_organismo';

    protected $fillable = [
        'ministerio_id',
        'persona_id',
        'cargo',
        'fecha_inicio',
        'fecha_fin',
        'acto_administrativo',
        'observaciones',
    ];

    protected $casts = [
        'fecha_inicio' => 'date:Y-m-d',
        'fecha_fin' => 'date:Y-m-d',
    ];

    public function ministerio(): BelongsTo
    {
        return $this->belongsTo(Ministerio::class, 'ministerio_id');
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }

    /**
     * Vigente hoy: ya asumió (o se desconoce la fecha) y no tiene cese o el cese es futuro
     */
    public function scopeVigente(Builder $query): Builder
    {
        $hoy = now()->toDateString();

        return $query
            ->where(fn ($q) => $q->whereNull('fecha_inicio')->orWhere('fecha_inicio', '<=', $hoy))
            ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $hoy));
    }
}
