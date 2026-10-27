<?php

namespace Tests\Feature\Permisos;

use App\Models\DocumentoAdjunto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\UsuariosDePrueba;
use Tests\TestCase;

/**
 * Escenarios de acceso por módulo (D-34): un módulo, varios, ninguno, administrador y revocación en sesión.
 */
class AccesoPorModuloTest extends TestCase
{
    use RefreshDatabase, UsuariosDePrueba;

    private function como(User $user): User
    {
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_usuario_solo_de_firma_digital(): void
    {
        $this->como($this->crearUsuario('USUARIO', ['FIRMA_DIGITAL' => 'OPERADOR']));

        $this->getJson('/api/firma-digital/entregas')->assertOk();
        $this->getJson('/api/firma-digital/parametros')->assertOk();
        // Catálogos de referencia que usa la pantalla de firma
        $this->getJson('/api/personas')->assertOk();
        $this->getJson('/api/ministerios')->assertOk();
        // El resto de los módulos
        $this->getJson('/api/expedientes')->assertForbidden();
        $this->postJson('/api/ministerios', ['nombre' => 'X'])->assertForbidden();
        $this->getJson('/api/areas-ti')->assertForbidden();
        $this->getJson('/api/servicios-areas')->assertForbidden();
        $this->getJson('/api/asignaciones-rol')->assertForbidden();
        $this->postJson('/api/export/preview')->assertForbidden();
        $this->getJson('/api/export/download')->assertForbidden();
        $this->getJson('/api/usuarios')->assertForbidden();
    }

    public function test_usuario_de_firma_digital_y_expedientes(): void
    {
        $this->como($this->crearUsuario('USUARIO', ['FIRMA_DIGITAL' => 'OPERADOR', 'EXPEDIENTES' => 'OPERADOR']));

        $this->getJson('/api/firma-digital/entregas')->assertOk();
        $this->getJson('/api/expedientes')->assertOk();
        $this->getJson('/api/expedientes/tablero')->assertOk();
        $this->postJson('/api/areas-ti', [])->assertForbidden();
        $this->postJson('/api/export/preview')->assertForbidden();
    }

    public function test_usuario_de_organismos(): void
    {
        $this->como($this->crearUsuario('USUARIO', ['ORGANISMOS' => 'OPERADOR']));

        $this->getJson('/api/ministerios')->assertOk();
        $this->getJson('/api/areas-ti')->assertOk();
        $this->getJson('/api/asignaciones-rol')->assertOk();
        $this->getJson('/api/tipos-rol')->assertOk();
        $this->postJson('/api/ministerios', [])->assertUnprocessable(); // pasa el permiso, falla la validación
        $this->getJson('/api/firma-digital/entregas')->assertForbidden();
        $this->getJson('/api/expedientes')->assertForbidden();
        $this->postJson('/api/personas', [])->assertForbidden();
        $this->postJson('/api/catalogo-servicios', [])->assertForbidden();
    }

    public function test_consulta_lee_pero_no_escribe(): void
    {
        $this->como($this->crearUsuario('USUARIO', ['FIRMA_DIGITAL' => 'CONSULTA', 'SERVICIOS' => 'CONSULTA']));

        $this->getJson('/api/firma-digital/entregas')->assertOk();
        $this->postJson('/api/firma-digital/entregas', [])->assertForbidden();
        $this->getJson('/api/catalogo-servicios')->assertOk();
        $this->postJson('/api/catalogo-servicios', [])->assertForbidden();
    }

    public function test_usuario_sin_modulos_solo_tiene_su_sesion_y_un_inicio_vacio(): void
    {
        $this->como($this->crearUsuario('USUARIO'));

        $this->getJson('/api/auth/user')->assertOk()->assertJsonPath('user.modulos', []);
        $this->getJson('/api/dashboard/stats')->assertOk()
            ->assertExactJson(['success' => true, 'data' => ['totales' => []]]);

        foreach (['/api/ministerios', '/api/personas', '/api/catalogo-servicios', '/api/firma-digital/entregas', '/api/expedientes'] as $url) {
            $this->getJson($url)->assertForbidden()->assertJsonPath('success', false);
        }
    }

    public function test_administrador_accede_a_todos_los_modulos(): void
    {
        $this->como($this->crearUsuario('ADMINISTRADOR'));

        foreach (['/api/ministerios', '/api/areas-ti', '/api/personas', '/api/catalogo-servicios',
            '/api/firma-digital/entregas', '/api/expedientes', '/api/usuarios'] as $url) {
            $this->getJson($url)->assertOk();
        }
        $this->postJson('/api/export/preview')->assertOk();

        $stats = $this->getJson('/api/dashboard/stats')->assertOk()->json('data');
        $this->assertArrayHasKey('ministerios', $stats['totales']);
        $this->assertArrayHasKey('personas', $stats['totales']);
        $this->assertArrayHasKey('servicios_activos', $stats['totales']);
    }

    public function test_el_inicio_muestra_solo_los_bloques_habilitados(): void
    {
        $this->como($this->crearUsuario('USUARIO', ['PADRON' => 'CONSULTA']));

        $data = $this->getJson('/api/dashboard/stats')->assertOk()->json('data');
        $this->assertSame(['personas'], array_keys($data['totales']));
        $this->assertArrayNotHasKey('ultimas_asignaciones', $data);
        $this->assertArrayNotHasKey('servicios_por_tipo', $data);
    }

    public function test_quitar_un_modulo_rige_en_el_request_siguiente_con_la_misma_sesion(): void
    {
        $user = $this->crearUsuario('USUARIO', ['FIRMA_DIGITAL' => 'OPERADOR']);
        $token = $user->createToken('sesion')->plainTextToken;
        $cabecera = ['Authorization' => "Bearer {$token}"];

        $this->getJson('/api/firma-digital/entregas', $cabecera)->assertOk();

        $user->modulos()->delete();
        $this->app['auth']->forgetGuards(); // nuevo request: sin el usuario resuelto en memoria

        $this->getJson('/api/firma-digital/entregas', $cabecera)->assertForbidden();
    }

    public function test_el_login_informa_roles_y_catalogo_de_modulos(): void
    {
        $user = $this->crearUsuario('USUARIO', ['FIRMA_DIGITAL' => 'CONSULTA'], email: 'login@test.local');

        $this->postJson('/api/auth/login', ['email' => 'login@test.local', 'password' => 'secreto-de-prueba'])
            ->assertOk()
            ->assertJsonPath('user.modulos', ['FIRMA_DIGITAL' => 'CONSULTA'])
            ->assertJsonPath('catalogo_modulos.0.clave', 'ORGANISMOS');

        $this->assertNotNull($user);
    }

    public function test_los_adjuntos_exigen_el_modulo_de_su_entidad(): void
    {
        $documento = DocumentoAdjunto::create([
            'documentable_type' => 'servicio_area', 'documentable_id' => 1, 'tipo_documento' => 'OTRO',
            'titulo' => 'Doc', 'archivo_nombre_original' => 'a.pdf', 'archivo_path' => 'documents/no-existe.pdf',
            'mime_type' => 'application/pdf', 'tamanio_bytes' => 1, 'sha256' => str_repeat('0', 64),
        ]);

        // ORGANISMOS pasa el middleware pero no gobierna los adjuntos de servicios
        $this->como($this->crearUsuario('USUARIO', ['ORGANISMOS' => 'OPERADOR']));
        $this->getJson("/api/documentos/{$documento->id}/descargar")->assertForbidden();

        // Con SERVICIOS llega al archivo (no existe en disco: 404)
        $this->como($this->crearUsuario('USUARIO', ['SERVICIOS' => 'CONSULTA']));
        $this->getJson("/api/documentos/{$documento->id}/descargar")->assertNotFound();
    }

    public function test_el_exportador_descarga_con_token(): void
    {
        $this->como($this->crearUsuario('USUARIO', ['EXPORTADOR' => 'CONSULTA']));

        $this->get('/api/export/download')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
