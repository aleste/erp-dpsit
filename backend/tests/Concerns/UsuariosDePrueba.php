<?php

namespace Tests\Concerns;

use App\Models\User;

/**
 * Crea usuarios de prueba con permisos por módulo (D-34). Acepta además los perfiles anteriores (EDITOR,
 * LECTOR) y los traduce como la migración 2026_10_27_000001: OPERADOR o CONSULTA en los módulos de gobernanza
 * y firma digital, más CONSULTA en el exportador.
 */
trait UsuariosDePrueba
{
    protected function crearUsuario(string $perfil, array $modulos = [], string $nombre = '', ?string $email = null): User
    {
        $equivalencia = match ($perfil) {
            'EDITOR' => 'OPERADOR',
            'LECTOR' => 'CONSULTA',
            default => null,
        };

        $user = User::create([
            'name' => $nombre ?: "Usuario {$perfil}",
            'email' => $email ?? uniqid('u').'@test.local',
            'password' => 'secreto-de-prueba',
            'perfil_acceso' => $perfil === User::ADMINISTRADOR ? User::ADMINISTRADOR : User::USUARIO,
            'activo' => true,
        ]);

        if ($equivalencia) {
            $modulos += [
                'ORGANISMOS' => $equivalencia, 'PADRON' => $equivalencia, 'SERVICIOS' => $equivalencia,
                'FIRMA_DIGITAL' => $equivalencia, 'EXPORTADOR' => 'CONSULTA',
            ];
        }

        foreach ($modulos as $modulo => $rol) {
            $user->modulos()->create(['modulo' => $modulo, 'rol' => $rol]);
        }

        return $user->load('modulos');
    }
}
