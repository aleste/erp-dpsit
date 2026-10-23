<?php

namespace Tests\Feature\Expedientes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermisosModuloTest extends TestCase
{
    use ExpedientesTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-23 10:00:00');
        $this->sembrarCatalogos();
    }

    public function test_sin_token_responde_401(): void
    {
        $this->getJson('/api/expedientes')->assertStatus(401);
        $this->getJson('/api/expedientes/exportar')->assertStatus(401);
    }

    public function test_un_editor_global_sin_rol_en_el_modulo_no_accede(): void
    {
        $this->actuarComo($this->usuario('EDITOR'));

        $this->getJson('/api/expedientes')->assertStatus(403)->assertJsonPath('rol_requerido', 'CONSULTA');
    }

    public function test_consulta_ve_pero_no_registra(): void
    {
        $this->actuarComo($this->usuario('LECTOR', 'CONSULTA'));

        $this->getJson('/api/expedientes')->assertOk();
        $this->getJson('/api/expedientes/tablero')->assertOk();
        $this->postJson('/api/expedientes', ['numero' => 'EX-2026-1- -GDEBA-X', 'fecha_ingreso' => '2026-10-01'])->assertStatus(403);
    }

    public function test_operador_registra_pero_no_reabre_ni_corrige_fechas(): void
    {
        $this->actuarComo($this->usuario('LECTOR', 'OPERADOR'));
        $intervencion = $this->ingresar();

        $this->postJson("/api/expedientes/intervenciones/{$intervencion['id']}/reabrir", ['motivo' => 'x', 'estado_id' => 1])->assertStatus(403);
        $this->postJson("/api/expedientes/intervenciones/{$intervencion['id']}/corregir-fechas", ['motivo' => 'x'])->assertStatus(403);
    }

    public function test_administrador_global_accede_sin_rol_explicito(): void
    {
        $this->actuarComo($this->usuario('ADMINISTRADOR'));

        $this->getJson('/api/expedientes')->assertOk();
        $this->ingresar();
    }

    public function test_el_login_informa_los_roles_por_modulo(): void
    {
        $user = $this->usuario('LECTOR', 'SUPERVISOR');

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secreto-de-prueba'])
            ->assertOk()
            ->assertJsonPath('user.modulos.EXPEDIENTES', 'SUPERVISOR');
    }
}
