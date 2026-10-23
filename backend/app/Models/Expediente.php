<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Identidad de un expediente. Sus pases por el área son intervenciones (D-30).
 */
class Expediente extends Model
{
    use SoftDeletes;

    private const RE_GDE = '/^EX-(\d{4})-\d{6,9}-\s*-GDEBA-([A-Z0-9]+)$/';

    protected $table = 'expedientes';

    protected $fillable = [
        'numero',
        'numero_busqueda',
        'formato',
        'anio',
        'reparticion',
        'ministerio_id',
        'caratula',
    ];

    protected $casts = [
        'anio' => 'integer',
    ];

    public function ministerio(): BelongsTo
    {
        return $this->belongsTo(Ministerio::class, 'ministerio_id');
    }

    public function intervenciones(): HasMany
    {
        return $this->hasMany(ExpedienteIntervencion::class, 'expediente_id');
    }

    // Intervención más reciente: define la situación actual del expediente
    public function intervencionActual(): HasOne
    {
        return $this->hasOne(ExpedienteIntervencion::class, 'expediente_id')->latestOfMany('numero_intervencion');
    }

    /**
     * Normaliza un número tal como se escribe en la planilla o en GDE:
     * sin espacios en los extremos, mayúsculas y espacios internos unificados.
     */
    public static function normalizarNumero(string $numero): string
    {
        return mb_strtoupper(preg_replace('/\s+/', ' ', trim($numero)));
    }

    // Clave de búsqueda tolerante: solo letras y dígitos (EX2026123GDEBA... encuentra EX-2026-123- -GDEBA-...)
    public static function claveBusqueda(string $numero): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($numero));
    }

    /**
     * Atributos derivados del número: formato GDE u otro, año y repartición
     */
    public static function atributosDeNumero(string $numero): array
    {
        $normalizado = self::normalizarNumero($numero);
        $esGde = preg_match(self::RE_GDE, $normalizado, $m) === 1;

        return [
            'numero' => $normalizado,
            'numero_busqueda' => self::claveBusqueda($normalizado),
            'formato' => $esGde ? 'GDE' : 'OTRO',
            'anio' => $esGde ? (int) $m[1] : (preg_match('/(20\d{2})/', $normalizado, $a) ? (int) $a[1] : null),
            'reparticion' => $esGde ? $m[2] : null,
        ];
    }
}
