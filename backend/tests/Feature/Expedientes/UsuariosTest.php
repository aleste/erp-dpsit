<?php

namespace Tests\Feature\Expedientes;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsuariosTest extends TestCase
{
    use ExpedientesTestHelpers, RefreshDatabase;

    public function test_solo_el_administrador_gestiona_usuarios(): void
    {
        $this->actuarComo($this->usuario('EDITOR'));
        $this->getJson('/api/usuarios')->assertStatus(403);

        $this->actuarComo($this->usuario('ADMINISTRADOR'));
        $this->getJson('/api/usuarios')->assertOk()->assertJsonPath('meta.modulos.EXPEDIENTES.2', 'SUPERVISOR');
    }

    public function test_alta_con_rol_en_un_modulo(): void
    {
        $this->actuarComo($this->usuario('ADMINISTRADOR'));

        $this->postJson('/api/usuarios', [
            'name' => 'Integrante del área',
            'email' => 'integrante@test.local',
            'perfil_acceso' => 'USUARIO',
            'password' => 'clave-segura-1',
            'modulos' => [['modulo' => 'EXPEDIENTES', 'rol' => 'OPERADOR']],
        ])->assertCreated()->assertJsonMissingPath('data.password');

        $user = User::where('email', 'integrante@test.local')->first();
        $this->assertTrue(Hash::check('clave-segura-1', $user->password));
        $this->assertSame('OPERADOR', $user->rolEnModulo('EXPEDIENTES'));
    }

    public function test_rechaza_un_rol_que_no_existe_en_el_modulo(): void
    {
        $this->actuarComo($this->usuario('ADMINISTRADOR'));

        $this->postJson('/api/usuarios', [
            'name' => 'X', 'email' => 'x@test.local', 'perfil_acceso' => 'USUARIO', 'password' => 'clave-segura-1',
            'modulos' => [['modulo' => 'EXPEDIENTES', 'rol' => 'EDITOR']],
        ])->assertStatus(422)->assertJsonValidationErrors('modulos.0.rol');
    }

    public function test_un_administrador_no_puede_desactivarse_y_desactivar_revoca_sesiones(): void
    {
        $admin = $this->actuarComo($this->usuario('ADMINISTRADOR'));

        $this->putJson("/api/usuarios/{$admin->id}", [
            'name' => $admin->name, 'email' => $admin->email, 'perfil_acceso' => 'ADMINISTRADOR', 'activo' => false,
        ])->assertStatus(422);

        $otro = $this->usuario('LECTOR', 'OPERADOR');
        $otro->createToken('sesion');
        $this->putJson("/api/usuarios/{$otro->id}", [
            'name' => $otro->name, 'email' => $otro->email, 'perfil_acceso' => 'USUARIO', 'activo' => false,
        ])->assertOk();

        $this->assertSame(0, $otro->tokens()->count());
        $this->assertFalse($otro->fresh()->puedeEnModulo('EXPEDIENTES', 'CONSULTA'));
    }
}
