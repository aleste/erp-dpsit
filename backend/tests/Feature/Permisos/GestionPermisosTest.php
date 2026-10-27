<?php

namespace Tests\Feature\Permisos;

use App\Models\AuditLog;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\UsuariosDePrueba;
use Tests\TestCase;

/**
 * Administración de permisos (D-34) y baja de personas por operadores (D-35)
 */
class GestionPermisosTest extends TestCase
{
    use RefreshDatabase, UsuariosDePrueba;

    private function datos(User $user, array $cambios = []): array
    {
        return array_merge([
            'name' => $user->name, 'email' => $user->email, 'perfil_acceso' => $user->perfil_acceso, 'activo' => true,
        ], $cambios);
    }

    public function test_un_usuario_no_puede_cambiar_sus_permisos_ni_los_de_otros(): void
    {
        $yo = $this->crearUsuario('USUARIO', ['PADRON' => 'OPERADOR']);
        $otro = $this->crearUsuario('USUARIO');
        Sanctum::actingAs($yo);

        $this->putJson("/api/usuarios/{$yo->id}", $this->datos($yo, ['perfil_acceso' => 'ADMINISTRADOR']))->assertForbidden();
        $this->putJson("/api/usuarios/{$otro->id}", $this->datos($otro, ['modulos' => [['modulo' => 'PADRON', 'rol' => 'OPERADOR']]]))->assertForbidden();
        $this->postJson('/api/usuarios', [])->assertForbidden();

        // Ningún otro endpoint acepta campos de permisos
        $this->putJson("/api/personas/{$this->persona()->id}", ['apellido' => 'A', 'nombre' => 'B', 'perfil_acceso' => 'ADMINISTRADOR']);
        $this->assertSame(User::USUARIO, $yo->fresh()->perfil_acceso);
    }

    public function test_alta_sin_modulos_por_defecto_y_perfiles_anteriores_rechazados(): void
    {
        Sanctum::actingAs($this->crearUsuario('ADMINISTRADOR'));

        $id = $this->postJson('/api/usuarios', [
            'name' => 'Nuevo', 'email' => 'nuevo@test.local', 'perfil_acceso' => 'USUARIO', 'password' => 'clave-segura-1',
        ])->assertCreated()->json('data.id');
        $this->assertSame([], User::find($id)->modulosAcceso());

        $this->postJson('/api/usuarios', [
            'name' => 'Viejo', 'email' => 'viejo@test.local', 'perfil_acceso' => 'EDITOR', 'password' => 'clave-segura-1',
        ])->assertUnprocessable()->assertJsonValidationErrors('perfil_acceso');

        // El default de la columna también es de mínimo privilegio
        $sinPerfil = DB::table('users')->insertGetId(['name' => 'Directo', 'email' => 'directo@test.local', 'password' => 'x']);
        $this->assertSame('USUARIO', DB::table('users')->find($sinPerfil)->perfil_acceso);
    }

    public function test_un_administrador_no_guarda_roles_por_modulo(): void
    {
        Sanctum::actingAs($this->crearUsuario('ADMINISTRADOR'));
        $user = $this->crearUsuario('USUARIO', ['PADRON' => 'CONSULTA']);

        $this->putJson("/api/usuarios/{$user->id}", $this->datos($user, [
            'perfil_acceso' => 'ADMINISTRADOR', 'modulos' => [['modulo' => 'PADRON', 'rol' => 'OPERADOR']],
        ]))->assertOk();

        $this->assertSame(0, $user->modulos()->count());
        $this->assertSame('SUPERVISOR', $user->fresh()->rolEnModulo('EXPEDIENTES'));
    }

    public function test_siempre_queda_un_administrador_activo(): void
    {
        $unico = $this->crearUsuario('ADMINISTRADOR');
        Sanctum::actingAs($unico);

        // Autoprotección
        $this->putJson("/api/usuarios/{$unico->id}", $this->datos($unico, ['perfil_acceso' => 'USUARIO']))->assertUnprocessable();

        // Con dos administradores, uno puede degradar al otro; luego el restante ya no puede ser degradado
        $segundo = $this->crearUsuario('ADMINISTRADOR');
        $this->putJson("/api/usuarios/{$segundo->id}", $this->datos($segundo, ['perfil_acceso' => 'USUARIO']))->assertOk();

        // Carrera: dos administradores se degradan entre sí a la vez. Se simula con la sesión de un
        // administrador que ya fue degradado en la base; el recuento bajo bloqueo rechaza la segunda baja.
        $tercero = $this->crearUsuario('ADMINISTRADOR');
        Sanctum::actingAs($tercero);
        DB::table('users')->where('id', $tercero->id)->update(['perfil_acceso' => 'USUARIO']);

        $this->putJson("/api/usuarios/{$unico->id}", $this->datos($unico, ['activo' => false]))
            ->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'al menos un administrador'));
        $this->assertTrue($unico->fresh()->activo);
    }

    public function test_los_cambios_de_permisos_quedan_en_la_bitacora(): void
    {
        $admin = $this->crearUsuario('ADMINISTRADOR');
        Sanctum::actingAs($admin);
        $user = $this->crearUsuario('USUARIO', ['PADRON' => 'CONSULTA']);

        $this->putJson("/api/usuarios/{$user->id}", $this->datos($user, [
            'modulos' => [['modulo' => 'PADRON', 'rol' => 'OPERADOR'], ['modulo' => 'FIRMA_DIGITAL', 'rol' => 'CONSULTA']],
        ]))->assertOk();

        $log = AuditLog::where('accion', 'PERMISOS')->where('registro_id', $user->id)->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(['PADRON:CONSULTA'], $log->valores_anteriores['modulos']);
        $this->assertSame(['FIRMA_DIGITAL:CONSULTA', 'PADRON:OPERADOR'], $log->valores_nuevos['modulos']);
        $this->assertStringContainsString('FIRMA_DIGITAL: sin acceso → CONSULTA', $log->detalles);
        // Sin cambios de datos personales no se registra un UPDATE
        $this->assertSame(0, AuditLog::where('accion', 'UPDATE')->where('modulo', 'USUARIOS')->count());

        $this->getJson('/api/usuarios')->assertOk()
            ->assertJsonPath('data.1.ultimo_cambio_permisos.autor', $admin->name);
    }

    public function test_listado_filtrado_por_modulo_incluye_a_los_administradores(): void
    {
        $admin = $this->crearUsuario('ADMINISTRADOR', [], 'A admin');
        Sanctum::actingAs($admin);
        $this->crearUsuario('USUARIO', ['FIRMA_DIGITAL' => 'CONSULTA'], 'B firma');
        $this->crearUsuario('USUARIO', ['PADRON' => 'CONSULTA'], 'C padron');

        $nombres = collect($this->getJson('/api/usuarios?modulo=FIRMA_DIGITAL')->assertOk()->json('data'))->pluck('name')->all();
        $this->assertSame(['A admin', 'B firma'], $nombres);
    }

    public function test_un_operador_de_firma_da_de_alta_modifica_y_borra_una_persona_sin_vinculos(): void
    {
        Sanctum::actingAs($this->crearUsuario('USUARIO', ['FIRMA_DIGITAL' => 'OPERADOR']));

        $id = $this->postJson('/api/personas', ['apellido' => 'Carga', 'nombre' => 'Errónea'])->assertCreated()->json('data.id');
        $this->putJson("/api/personas/{$id}", ['apellido' => 'Carga', 'nombre' => 'Corregida'])->assertOk();
        $this->deleteJson("/api/personas/{$id}")->assertOk();
        $this->assertSoftDeleted('personas', ['id' => $id]);
    }

    public function test_un_operador_no_borra_una_persona_vinculada_pero_el_administrador_si(): void
    {
        $persona = $this->persona();
        $usuarioVinculado = $this->crearUsuario('USUARIO');
        $usuarioVinculado->update(['persona_id' => $persona->id]);

        Sanctum::actingAs($this->crearUsuario('USUARIO', ['PADRON' => 'OPERADOR']));
        $this->deleteJson("/api/personas/{$persona->id}")->assertUnprocessable()
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'un usuario del sistema'));

        Sanctum::actingAs($this->crearUsuario('ADMINISTRADOR'));
        $this->deleteJson("/api/personas/{$persona->id}")->assertOk();
    }

    public function test_consulta_del_padron_no_borra_personas(): void
    {
        Sanctum::actingAs($this->crearUsuario('USUARIO', ['PADRON' => 'CONSULTA', 'ORGANISMOS' => 'OPERADOR']));

        $this->deleteJson("/api/personas/{$this->persona()->id}")->assertForbidden();
    }

    private function persona(): Persona
    {
        return Persona::create(['apellido' => 'Prueba', 'nombre' => 'Persona', 'activo' => true]);
    }
}
