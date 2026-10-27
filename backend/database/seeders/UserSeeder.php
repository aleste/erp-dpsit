<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Usuarios de prueba (solo desarrollo, SEC-05): un administrador y combinaciones de permisos por módulo (D-34)
 * para probar los escenarios típicos hasta el pase a producción.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $gobernanza = fn (string $rol) => [
            'ORGANISMOS' => $rol, 'PADRON' => $rol, 'SERVICIOS' => $rol, 'FIRMA_DIGITAL' => $rol, 'EXPORTADOR' => 'CONSULTA',
        ];

        $users = [
            ['name' => 'Administrador DPSIT', 'email' => 'admin@dpsit.gob.ar', 'clave' => 'admin123', 'perfil' => User::ADMINISTRADOR, 'modulos' => []],
            ['name' => 'Operador Editor TI', 'email' => 'editor@dpsit.gob.ar', 'clave' => 'editor123', 'perfil' => User::USUARIO, 'modulos' => $gobernanza('OPERADOR')],
            ['name' => 'Auditor / Lector', 'email' => 'lector@dpsit.gob.ar', 'clave' => 'lector123', 'perfil' => User::USUARIO, 'modulos' => $gobernanza('CONSULTA')],
            ['name' => 'Prueba Firma Digital', 'email' => 'firma@dpsit.gob.ar', 'clave' => 'firma123', 'perfil' => User::USUARIO, 'modulos' => ['FIRMA_DIGITAL' => 'OPERADOR']],
            ['name' => 'Prueba Firma y Expedientes', 'email' => 'firma.expedientes@dpsit.gob.ar', 'clave' => 'firma123', 'perfil' => User::USUARIO, 'modulos' => ['FIRMA_DIGITAL' => 'OPERADOR', 'EXPEDIENTES' => 'OPERADOR']],
            ['name' => 'Prueba Organismos', 'email' => 'organismos@dpsit.gob.ar', 'clave' => 'organismos123', 'perfil' => User::USUARIO, 'modulos' => ['ORGANISMOS' => 'OPERADOR']],
            ['name' => 'Prueba Sin Módulos', 'email' => 'sinmodulos@dpsit.gob.ar', 'clave' => 'sinmodulos123', 'perfil' => User::USUARIO, 'modulos' => []],
        ];

        foreach ($users as $u) {
            $user = User::updateOrCreate(['email' => $u['email']], [
                'name' => $u['name'],
                'password' => Hash::make($u['clave']),
                'perfil_acceso' => $u['perfil'],
                'activo' => true,
            ]);

            $user->modulos()->delete();
            foreach ($u['modulos'] as $modulo => $rol) {
                $user->modulos()->create(['modulo' => $modulo, 'rol' => $rol]);
            }
        }
    }
}
