<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class DispositivoFirma extends Model
{
    use HasFactory, SoftDeletes;

    public const TIPOS = ['TOKEN_USB', 'TARJETA_CHIP', 'OTRO'];

    public const ESTADOS = ['DISPONIBLE', 'ASIGNADO', 'DEVUELTO', 'EXTRAVIADO', 'DADO_DE_BAJA'];

    protected $table = 'dispositivos_firma';

    protected $fillable = [
        'tipo_dispositivo',
        'marca',
        'modelo',
        'numero_serie',
        'estado',
        'observaciones',
    ];

    public function entregas(): HasMany
    {
        return $this->hasMany(EntregaToken::class, 'dispositivo_firma_id');
    }

    public function entregaActual(): HasOne
    {
        return $this->hasOne(EntregaToken::class, 'dispositivo_firma_id')
            ->where('estado', EntregaToken::ESTADO_ASIGNADA);
    }
}
