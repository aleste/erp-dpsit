<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class EntregaToken extends Model
{
    use HasFactory, SoftDeletes;

    public const ESTADO_ASIGNADA = 'ASIGNADA';

    public const ESTADOS = ['ASIGNADA', 'DEVUELTA', 'REEMPLAZADA', 'EXTRAVIADA', 'REVOCADA'];

    protected $table = 'entregas_token';

    protected $fillable = [
        'persona_id',
        'dispositivo_firma_id',
        'ministerio_id',
        'organismo_externo',
        'cargo_funcion',
        'fecha_entrega',
        'fecha_fin',
        'estado',
        'motivo_fin',
        'observaciones',
        'registrado_por_user_id',
    ];

    protected $casts = [
        'fecha_entrega' => 'date:Y-m-d',
        'fecha_fin' => 'date:Y-m-d',
    ];

    protected $appends = ['organismo_nombre'];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }

    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(DispositivoFirma::class, 'dispositivo_firma_id');
    }

    public function ministerio(): BelongsTo
    {
        return $this->belongsTo(Ministerio::class, 'ministerio_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    public function certificados(): HasMany
    {
        return $this->hasMany(CertificadoFirma::class, 'entrega_token_id');
    }

    // Certificado más reciente de la entrega (el único que puede estar vigente)
    public function certificadoActual(): HasOne
    {
        return $this->hasOne(CertificadoFirma::class, 'entrega_token_id')->latestOfMany(['fecha_inicio', 'id']);
    }

    // Accessor: organismo del ERP o externo, para mostrar en listados
    public function getOrganismoNombreAttribute(): ?string
    {
        if ($this->ministerio_id && $this->relationLoaded('ministerio') && $this->ministerio) {
            return $this->ministerio->nombre;
        }

        return $this->organismo_externo;
    }
}
