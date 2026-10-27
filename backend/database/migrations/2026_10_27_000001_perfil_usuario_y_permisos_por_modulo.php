<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permisos por módulo para todo el sistema (D-34). El perfil global queda en ADMINISTRADOR o USUARIO; los
 * EDITOR y LECTOR existentes (hoy solo usuarios de prueba) reciben el acceso equivalente al que tenían:
 * OPERADOR o CONSULTA en los módulos de gobernanza y firma digital. Sus roles de EXPEDIENTES no cambian.
 *
 * Autocontenida a propósito (no usa los modelos), para que siga siendo reproducible si el catálogo cambia.
 */
return new class extends Migration
{
    private const MODULOS = ['ORGANISMOS', 'PADRON', 'SERVICIOS', 'FIRMA_DIGITAL'];

    public function up(): void
    {
        DB::statement("ALTER TABLE users ALTER COLUMN perfil_acceso SET DEFAULT 'USUARIO'");

        DB::transaction(function () {
            $ahora = now();
            $usuarios = DB::table('users')->whereIn('perfil_acceso', ['EDITOR', 'LECTOR'])->get(['id', 'perfil_acceso']);

            foreach ($usuarios as $u) {
                $rol = $u->perfil_acceso === 'EDITOR' ? 'OPERADOR' : 'CONSULTA';
                $filas = collect(self::MODULOS)->map(fn ($m) => ['modulo' => $m, 'rol' => $rol])
                    ->push(['modulo' => 'EXPORTADOR', 'rol' => 'CONSULTA']);

                foreach ($filas as $f) {
                    DB::table('user_modulos')->insertOrIgnore([
                        'user_id' => $u->id, 'modulo' => $f['modulo'], 'rol' => $f['rol'],
                        'created_at' => $ahora, 'updated_at' => $ahora,
                    ]);
                }

                DB::table('users')->where('id', $u->id)->update(['perfil_acceso' => 'USUARIO', 'updated_at' => $ahora]);
            }
        });
    }

    /**
     * Reversión aproximada: quien tenga OPERADOR en algún módulo de gobernanza vuelve a EDITOR y el resto
     * de los USUARIO, a LECTOR. Se borran los roles de módulos que no existían antes (se conserva EXPEDIENTES).
     */
    public function down(): void
    {
        DB::transaction(function () {
            $editores = DB::table('user_modulos')->whereIn('modulo', self::MODULOS)->where('rol', 'OPERADOR')->pluck('user_id');

            DB::table('users')->where('perfil_acceso', 'USUARIO')->whereIn('id', $editores)->update(['perfil_acceso' => 'EDITOR']);
            DB::table('users')->where('perfil_acceso', 'USUARIO')->update(['perfil_acceso' => 'LECTOR']);
            DB::table('user_modulos')->where('modulo', '!=', 'EXPEDIENTES')->delete();
        });

        DB::statement("ALTER TABLE users ALTER COLUMN perfil_acceso SET DEFAULT 'LECTOR'");
    }
};
