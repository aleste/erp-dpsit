<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rol de un usuario dentro de un módulo (D-26). Los roles de cada módulo se listan de menor a mayor.
 */
class UserModulo extends Model
{
    public const EXPEDIENTES = 'EXPEDIENTES';

    public const ROLES = [
        self::EXPEDIENTES => ['CONSULTA', 'OPERADOR', 'SUPERVISOR'],
    ];

    protected $table = 'user_modulos';

    protected $fillable = ['user_id', 'modulo', 'rol'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * ¿El rol $rol alcanza al menos el nivel $minimo dentro del módulo?
     */
    public static function alcanza(string $modulo, ?string $rol, string $minimo): bool
    {
        $roles = self::ROLES[$modulo] ?? [];
        $actual = $rol === null ? false : array_search($rol, $roles, true);
        $requerido = array_search($minimo, $roles, true);

        return $actual !== false && $requerido !== false && $actual >= $requerido;
    }
}
