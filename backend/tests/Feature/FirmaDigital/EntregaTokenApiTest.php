<?php

namespace Tests\Feature\FirmaDigital;

use App\Models\CertificadoFirma;
use App\Models\DispositivoFirma;
use App\Models\EntregaToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntregaTokenApiTest extends TestCase
{
    use FirmaDigitalTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-23 10:00:00');
    }

    private function payloadEntrega(int $personaId, array $extra = []): array
    {
        return array_merge([
            'persona_id' => $personaId,
            'fecha_entrega' => '2026-10-01',
            'dispositivo' => [
                'tipo_dispositivo' => 'TOKEN_USB',
                'marca' => 'MarcaDemo',
                'modelo' => 'M1',
                'numero_serie' => 'SN-'.uniqid(),
            ],
        ], $extra);
    }

    public function test_sin_token_responde_401(): void
    {
        $this->getJson('/api/firma-digital/entregas')->assertStatus(401);
    }

    public function test_lector_consulta_pero_no_registra(): void
    {
        $this->actuarComo('LECTOR');
        $persona = $this->crearPersona();

        $this->getJson('/api/firma-digital/entregas')->assertOk()->assertJsonPath('success', true);
        $this->postJson('/api/firma-digital/entregas', $this->payloadEntrega($persona->id))->assertStatus(403);
    }

    public function test_editor_registra_entrega_a_persona_sin_organismo_ni_cuil(): void
    {
        $this->actuarComo('EDITOR');

        // Persona externa al ERP: sin CUIL, sin teléfono, solo nombre, documento y email
        $persona = $this->postJson('/api/personas', [
            'apellido' => 'Externo',
            'nombre' => 'Médico',
            'tipo_documento' => 'DNI',
            'numero_documento' => '30111222',
            'email_principal' => 'medico.externo@test.local',
        ])->assertCreated()->json('data');

        $respuesta = $this->postJson('/api/firma-digital/entregas', $this->payloadEntrega($persona['id'], [
            'organismo_externo' => 'Hospital Privado de Pruebas',
            'cargo_funcion' => 'Autoridad de Registro',
        ]))->assertCreated();

        $respuesta->assertJsonPath('data.organismo_externo', 'Hospital Privado de Pruebas')
            ->assertJsonPath('data.ministerio_id', null)
            ->assertJsonPath('data.certificado_actual.fecha_inicio', '2026-10-01')
            ->assertJsonPath('data.certificado_actual.fecha_vencimiento', '2027-10-01')
            ->assertJsonPath('data.certificado_actual.fecha_recordatorio', '2027-09-01')
            ->assertJsonPath('data.certificado_actual.recordatorio_estado', 'PENDIENTE');

        $this->assertSame('ASIGNADO', DispositivoFirma::first()->estado);
    }

    public function test_no_admite_ministerio_y_organismo_externo_a_la_vez(): void
    {
        $this->actuarComo('EDITOR');
        $persona = $this->crearPersona();
        $ministerio = $this->crearMinisterio();

        $this->postJson('/api/firma-digital/entregas', $this->payloadEntrega($persona->id, [
            'ministerio_id' => $ministerio->id,
            'organismo_externo' => 'Otro organismo',
        ]))->assertStatus(422)->assertJsonValidationErrors('organismo_externo');
    }

    public function test_un_dispositivo_asignado_no_puede_entregarse_otra_vez(): void
    {
        $this->actuarComo('EDITOR');
        $persona = $this->crearPersona();

        $this->postJson('/api/firma-digital/entregas', $this->payloadEntrega($persona->id))->assertCreated();
        $dispositivoId = DispositivoFirma::first()->id;

        $this->postJson('/api/firma-digital/entregas', [
            'persona_id' => $this->crearPersona()->id,
            'fecha_entrega' => '2026-10-05',
            'dispositivo_firma_id' => $dispositivoId,
        ])->assertStatus(422);
    }

    public function test_finalizar_devuelve_el_dispositivo_y_cancela_el_recordatorio(): void
    {
        $this->actuarComo('EDITOR');
        $persona = $this->crearPersona();
        $id = $this->postJson('/api/firma-digital/entregas', $this->payloadEntrega($persona->id))->json('data.id');

        $this->postJson("/api/firma-digital/entregas/{$id}/finalizar", [
            'estado' => 'DEVUELTA',
            'fecha_fin' => '2026-10-20',
            'motivo_fin' => 'Baja del agente',
        ])->assertOk();

        $entrega = EntregaToken::with(['dispositivo', 'certificadoActual'])->find($id);
        $this->assertSame('DEVUELTA', $entrega->estado);
        $this->assertSame('DEVUELTO', $entrega->dispositivo->estado);
        $this->assertSame(CertificadoFirma::RECORDATORIO_CANCELADO, $entrega->certificadoActual->recordatorio_estado);
    }

    public function test_renovar_certificado_conserva_el_historial(): void
    {
        $this->actuarComo('EDITOR');
        $persona = $this->crearPersona();
        $id = $this->postJson('/api/firma-digital/entregas', $this->payloadEntrega($persona->id))->json('data.id');

        $this->postJson("/api/firma-digital/entregas/{$id}/certificados", ['fecha_inicio' => '2027-09-15'])
            ->assertCreated()
            ->assertJsonPath('data.fecha_vencimiento', '2028-09-15');

        $entrega = EntregaToken::with(['certificados', 'certificadoActual'])->find($id);
        $this->assertCount(2, $entrega->certificados);
        $this->assertSame('2027-09-15', $entrega->certificadoActual->fecha_inicio->toDateString());
        $this->assertSame(CertificadoFirma::RECORDATORIO_CANCELADO, $entrega->certificados->firstWhere('fecha_inicio', $entrega->certificados->min('fecha_inicio'))->recordatorio_estado);
    }

    public function test_filtra_por_estado_del_certificado(): void
    {
        $this->actuarComo('LECTOR');
        $this->crearEntregaConCertificado($this->crearPersona(), '2026-11-10', '2026-10-11'); // próximo a vencer
        $this->crearEntregaConCertificado($this->crearPersona(), '2026-09-30', '2026-08-31'); // vencido
        $this->crearEntregaConCertificado($this->crearPersona(), '2027-06-30', '2027-05-31'); // vigente

        $this->getJson('/api/firma-digital/entregas?estado_certificado=PROXIMO_A_VENCER')
            ->assertOk()->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.certificado_actual.estado_certificado', 'PROXIMO_A_VENCER');

        $this->getJson('/api/firma-digital/entregas?estado_certificado=VENCIDO')->assertJsonPath('data.total', 1);
        $this->getJson('/api/firma-digital/entregas?estado_certificado=VIGENTE')->assertJsonPath('data.total', 1);

        $this->getJson('/api/firma-digital/entregas')
            ->assertJsonPath('stats.asignados', 3)
            ->assertJsonPath('stats.proximos_a_vencer', 1)
            ->assertJsonPath('stats.vencidos', 1);
    }

    public function test_solo_administrador_elimina_entregas(): void
    {
        $this->actuarComo('EDITOR');
        $id = $this->postJson('/api/firma-digital/entregas', $this->payloadEntrega($this->crearPersona()->id))->json('data.id');

        $this->deleteJson("/api/firma-digital/entregas/{$id}")->assertStatus(403);

        $this->actuarComo('ADMINISTRADOR');
        $this->deleteJson("/api/firma-digital/entregas/{$id}")->assertOk();
        $this->assertSame('DISPONIBLE', DispositivoFirma::first()->estado);
    }
}
