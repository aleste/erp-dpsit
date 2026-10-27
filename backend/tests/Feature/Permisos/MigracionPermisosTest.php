<?php

namespace Tests\Feature\Permisos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Migración 2026_10_27_000001: EDITOR y LECTOR pasan a USUARIO con el acceso equivalente; ADMINISTRADOR no cambia
 */
class MigracionPermisosTest extends TestCase
{
    use RefreshDatabase;

    private function migracion(): object
    {
        return require database_path('migrations/2026_10_27_000001_perfil_usuario_y_permisos_por_modulo.php');
    }

    private function usuario(string $perfil, array $modulos = []): int
    {
        $id = DB::table('users')->insertGetId([
            'name' => $perfil, 'email' => uniqid('m').'@test.local', 'password' => 'x', 'perfil_acceso' => $perfil, 'activo' => true,
        ]);
        foreach ($modulos as $modulo => $rol) {
            DB::table('user_modulos')->insert(['user_id' => $id, 'modulo' => $modulo, 'rol' => $rol]);
        }

        return $id;
    }

    private function roles(int $id): array
    {
        return DB::table('user_modulos')->where('user_id', $id)->orderBy('modulo')->pluck('rol', 'modulo')->all();
    }

    public function test_convierte_los_perfiles_anteriores_y_se_revierte(): void
    {
        $admin = $this->usuario('ADMINISTRADOR');
        $editor = $this->usuario('EDITOR', ['EXPEDIENTES' => 'SUPERVISOR']);
        $lector = $this->usuario('LECTOR');

        $this->migracion()->up();

        $this->assertSame('ADMINISTRADOR', DB::table('users')->find($admin)->perfil_acceso);
        $this->assertSame([], $this->roles($admin));
        $this->assertSame('USUARIO', DB::table('users')->find($editor)->perfil_acceso);
        $this->assertSame([
            'EXPEDIENTES' => 'SUPERVISOR', 'EXPORTADOR' => 'CONSULTA', 'FIRMA_DIGITAL' => 'OPERADOR',
            'ORGANISMOS' => 'OPERADOR', 'PADRON' => 'OPERADOR', 'SERVICIOS' => 'OPERADOR',
        ], $this->roles($editor));
        $this->assertSame('CONSULTA', $this->roles($lector)['ORGANISMOS']);

        $this->migracion()->down();

        $this->assertSame('EDITOR', DB::table('users')->find($editor)->perfil_acceso);
        $this->assertSame('LECTOR', DB::table('users')->find($lector)->perfil_acceso);
        $this->assertSame(['EXPEDIENTES' => 'SUPERVISOR'], $this->roles($editor));

        $this->migracion()->up(); // deja la base como la espera el resto de la suite
    }
}
