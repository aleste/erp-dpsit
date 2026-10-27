<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rol de un usuario dentro de un módulo (D-26, ampliado en D-34).
 *
 * Para agregar un módulo: una constante, su entrada en ROLES (roles de la escala común, de menor a mayor) y
 * en NOMBRES; luego proteger sus rutas con el middleware `modulo:` (el test de cobertura de rutas lo exige)
 * y declarar `modulo` en las páginas del frontend. La pantalla de usuarios y el menú lo toman de la API.
 */
class UserModulo extends Model
{
    public const ORGANISMOS = 'ORGANISMOS';

    public const PADRON = 'PADRON';

    public const SERVICIOS = 'SERVICIOS';

    public const FIRMA_DIGITAL = 'FIRMA_DIGITAL';

    public const EXPEDIENTES = 'EXPEDIENTES';

    public const EXPORTADOR = 'EXPORTADOR';

    /** Escala común de roles, de menor a mayor. Cada módulo usa un subconjunto. */
    public const ESCALA = ['CONSULTA', 'OPERADOR', 'SUPERVISOR'];

    public const ROLES = [
        self::ORGANISMOS => ['CONSULTA', 'OPERADOR'],
        self::PADRON => ['CONSULTA', 'OPERADOR'],
        self::SERVICIOS => ['CONSULTA', 'OPERADOR'],
        self::FIRMA_DIGITAL => ['CONSULTA', 'OPERADOR'],
        self::EXPEDIENTES => ['CONSULTA', 'OPERADOR', 'SUPERVISOR'],
        self::EXPORTADOR => ['CONSULTA'],
    ];

    public const NOMBRES = [
        self::ORGANISMOS => 'Organismos y roles',
        self::PADRON => 'Padrón de personas',
        self::SERVICIOS => 'Servicios tecnológicos',
        self::FIRMA_DIGITAL => 'Firma digital',
        self::EXPEDIENTES => 'Expedientes',
        self::EXPORTADOR => 'Exportador de contactos',
    ];

    protected $table = 'user_modulos';

    protected $fillable = ['user_id', 'modulo', 'rol'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Catálogo para la interfaz: [{ clave, nombre, roles }]
     */
    public static function catalogo(): array
    {
        return collect(self::ROLES)
            ->map(fn ($roles, $clave) => ['clave' => $clave, 'nombre' => self::NOMBRES[$clave] ?? $clave, 'roles' => $roles])
            ->values()
            ->all();
    }

    /**
     * ¿El rol $rol alcanza al menos el nivel $minimo dentro del módulo?
     * El rol debe existir en el módulo; el mínimo se compara en la escala común.
     */
    public static function alcanza(string $modulo, ?string $rol, string $minimo): bool
    {
        if ($rol === null || ! in_array($rol, self::ROLES[$modulo] ?? [], true)) {
            return false;
        }

        $actual = array_search($rol, self::ESCALA, true);
        $requerido = array_search($minimo, self::ESCALA, true);

        return $requerido !== false && $actual >= $requerido;
    }
}
