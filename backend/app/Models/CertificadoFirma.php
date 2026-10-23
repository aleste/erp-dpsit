<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificadoFirma extends Model
{
    use HasFactory;

    // Estados calculados del certificado (no se almacenan)
    public const VIGENTE = 'VIGENTE';

    public const PROXIMO_A_VENCER = 'PROXIMO_A_VENCER';

    public const VENCIDO = 'VENCIDO';

    public const NO_VIGENTE = 'NO_VIGENTE';

    public const ESTADOS_CERTIFICADO = [self::VIGENTE, self::PROXIMO_A_VENCER, self::VENCIDO, self::NO_VIGENTE];

    // Estados almacenados del recordatorio
    public const RECORDATORIO_PENDIENTE = 'PENDIENTE';

    public const RECORDATORIO_ENVIANDO = 'ENVIANDO';

    public const RECORDATORIO_ENVIADO = 'ENVIADO';

    public const RECORDATORIO_ERROR = 'ERROR';

    public const RECORDATORIO_SIN_EMAIL = 'SIN_EMAIL';

    public const RECORDATORIO_CANCELADO = 'CANCELADO';

    public const ESTADOS_RECORDATORIO = ['PENDIENTE', 'ENVIANDO', 'ENVIADO', 'ERROR', 'SIN_EMAIL', 'CANCELADO'];

    protected $table = 'certificados_firma';

    protected $fillable = [
        'entrega_token_id',
        'fecha_inicio',
        'fecha_vencimiento',
        'vencimiento_manual',
        'fecha_recordatorio',
        'recordatorio_manual',
        'recordatorio_estado',
        'recordatorio_enviado_at',
        'recordatorio_email',
        'recordatorio_intentos',
        'recordatorio_ultimo_error',
        'observaciones',
    ];

    protected $casts = [
        'fecha_inicio' => 'date:Y-m-d',
        'fecha_vencimiento' => 'date:Y-m-d',
        'fecha_recordatorio' => 'date:Y-m-d',
        'vencimiento_manual' => 'boolean',
        'recordatorio_manual' => 'boolean',
        'recordatorio_enviado_at' => 'datetime',
        'recordatorio_intentos' => 'integer',
    ];

    protected $appends = ['estado_certificado', 'dias_para_vencer'];

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(EntregaToken::class, 'entrega_token_id');
    }

    /**
     * Estado de vigencia. Única definición de las reglas, junto con scopeEstadoCertificado():
     * NO_VIGENTE si la entrega ya no está asignada; si no, según la fecha de vencimiento
     * y la anticipación configurada.
     */
    public function getEstadoCertificadoAttribute(): string
    {
        if ($this->relationLoaded('entrega') && $this->entrega && $this->entrega->estado !== EntregaToken::ESTADO_ASIGNADA) {
            return self::NO_VIGENTE;
        }

        $hoy = now()->startOfDay();
        $limiteProximo = $hoy->copy()->addDays(self::diasAnticipacion());

        if ($this->fecha_vencimiento->lt($hoy)) {
            return self::VENCIDO;
        }

        if ($this->fecha_vencimiento->lte($limiteProximo)) {
            return self::PROXIMO_A_VENCER;
        }

        return self::VIGENTE;
    }

    public function getDiasParaVencerAttribute(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->fecha_vencimiento, false);
    }

    // Filtra por estado calculado (mismas reglas que getEstadoCertificadoAttribute)
    public function scopeEstadoCertificado(Builder $query, string $estado): Builder
    {
        $hoy = now()->toDateString();
        $limiteProximo = now()->addDays(self::diasAnticipacion())->toDateString();

        if ($estado === self::NO_VIGENTE) {
            return $query->whereHas('entrega', fn ($q) => $q->where('estado', '!=', EntregaToken::ESTADO_ASIGNADA));
        }

        $query->whereHas('entrega', fn ($q) => $q->where('estado', EntregaToken::ESTADO_ASIGNADA));

        return match ($estado) {
            self::VENCIDO => $query->where('fecha_vencimiento', '<', $hoy),
            self::PROXIMO_A_VENCER => $query->whereBetween('fecha_vencimiento', [$hoy, $limiteProximo]),
            default => $query->where('fecha_vencimiento', '>', $limiteProximo),
        };
    }

    /**
     * Solo el certificado más reciente de cada entrega no eliminada
     * (mismo criterio que EntregaToken::certificadoActual: mayor fecha_inicio y, ante empate, mayor id)
     */
    public function scopeActuales(Builder $query): Builder
    {
        return $query->whereHas('entrega')
            ->whereIn('certificados_firma.id', function ($sub) {
                $sub->selectRaw('DISTINCT ON (entrega_token_id) id')
                    ->from('certificados_firma')
                    ->orderBy('entrega_token_id')
                    ->orderByDesc('fecha_inicio')
                    ->orderByDesc('id');
            });
    }

    public static function diasAnticipacion(): int
    {
        return (int) config('firma_digital.recordatorio_dias_anticipacion', 30);
    }
}
