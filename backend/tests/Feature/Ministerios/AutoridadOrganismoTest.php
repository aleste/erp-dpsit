<?php

namespace Tests\Feature\Ministerios;

use App\Models\AutoridadOrganismo;
use App\Models\Ministerio;
use App\Models\Persona;
use Database\Seeders\MinisterioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\FirmaDigital\FirmaDigitalTestHelpers;
use Tests\TestCase;

class AutoridadOrganismoTest extends TestCase
{
    use FirmaDigitalTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-24 10:00:00');
    }

    public function test_designar_cierra_la_autoridad_vigente_y_conserva_el_historial(): void
    {
        $this->actuarComo('EDITOR');
        $ministerio = $this->crearMinisterio();
        $anterior = $this->crearPersona(['apellido' => 'Anterior']);
        $nueva = $this->crearPersona(['apellido' => 'Nueva']);

        $this->postJson("/api/ministerios/{$ministerio->id}/autoridad", [
            'persona_id' => $anterior->id, 'cargo' => 'Ministra', 'fecha_inicio' => '2023-12-11',
        ])->assertCreated();

        $this->postJson("/api/ministerios/{$ministerio->id}/autoridad", [
            'persona_id' => $nueva->id, 'cargo' => 'Ministro', 'fecha_inicio' => '2026-10-20',
        ])->assertCreated();

        $this->assertSame('2026-10-19', AutoridadOrganismo::where('persona_id', $anterior->id)->first()->fecha_fin->toDateString());
        $this->assertSame($nueva->id, $ministerio->autoridadVigente()->value('persona_id'));

        $this->getJson("/api/ministerios/{$ministerio->id}")
            ->assertOk()
            ->assertJsonPath('data.autoridad_vigente.persona.apellido', 'Nueva')
            ->assertJsonCount(2, 'data.autoridades');
    }

    public function test_cierra_tambien_una_autoridad_con_cese_futuro(): void
    {
        $this->actuarComo('EDITOR');
        $ministerio = $this->crearMinisterio();
        AutoridadOrganismo::create([
            'ministerio_id' => $ministerio->id,
            'persona_id' => $this->crearPersona()->id,
            'fecha_inicio' => '2024-01-01',
            'fecha_fin' => '2027-12-10',
        ]);

        $this->postJson("/api/ministerios/{$ministerio->id}/autoridad", [
            'persona_id' => $this->crearPersona()->id, 'fecha_inicio' => '2026-10-24',
        ])->assertCreated();

        $this->assertSame(1, AutoridadOrganismo::where('ministerio_id', $ministerio->id)->vigente()->count());
    }

    public function test_rechaza_designar_a_la_misma_persona_vigente(): void
    {
        $this->actuarComo('EDITOR');
        $ministerio = $this->crearMinisterio();
        $persona = $this->crearPersona();

        $this->postJson("/api/ministerios/{$ministerio->id}/autoridad", ['persona_id' => $persona->id])->assertCreated();
        $this->postJson("/api/ministerios/{$ministerio->id}/autoridad", ['persona_id' => $persona->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('persona_id');
    }

    public function test_lector_no_puede_designar_ni_editor_eliminar(): void
    {
        $ministerio = $this->crearMinisterio();
        $persona = $this->crearPersona();

        $this->actuarComo('LECTOR');
        $this->postJson("/api/ministerios/{$ministerio->id}/autoridad", ['persona_id' => $persona->id])->assertForbidden();

        $autoridad = AutoridadOrganismo::create(['ministerio_id' => $ministerio->id, 'persona_id' => $persona->id]);
        $this->actuarComo('EDITOR');
        $this->deleteJson("/api/autoridades-organismo/{$autoridad->id}")->assertForbidden();
    }

    public function test_no_se_elimina_una_persona_que_es_autoridad_vigente(): void
    {
        $this->actuarComo('ADMINISTRADOR');
        $persona = $this->crearPersona();
        AutoridadOrganismo::create(['ministerio_id' => $this->crearMinisterio()->id, 'persona_id' => $persona->id]);

        $this->deleteJson("/api/personas/{$persona->id}")->assertStatus(422);
    }

    public function test_el_seeder_es_idempotente_y_reutiliza_los_ministerios_demo(): void
    {
        $demo = Ministerio::create(['codigo' => 'MS', 'nombre' => 'Ministerio de Salud', 'sigla' => 'MS']);
        $importado = Ministerio::create(['codigo' => 'ORG-HTC', 'nombre' => 'HONORABLE TRIBUNAL DE CUENTAS - HTC', 'orden_prioridad' => 100]);

        $this->seed(MinisterioSeeder::class);
        $this->seed(MinisterioSeeder::class);

        $this->assertSame(25, Ministerio::count());
        $this->assertSame('MSAL', $demo->fresh()->codigo);
        $this->assertSame('HTC', $importado->fresh()->codigo);
        $this->assertSame(25, AutoridadOrganismo::count());
        $this->assertSame(1, Persona::where('apellido', 'Kreplak')->count());
        $this->assertSame(2, Persona::where('apellido', 'Vila')->count());
    }
}
