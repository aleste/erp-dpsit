<?php

namespace Tests\Feature\AreasTi;

use App\Models\AreaTi;
use App\Models\AsignacionRol;
use App\Models\TipoRol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\FirmaDigital\FirmaDigitalTestHelpers;
use Tests\TestCase;

class FinalizarVigenciaTest extends TestCase
{
    use FirmaDigitalTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-25 10:00:00');
    }

    private function crearAsignacion(array $atributos = []): AsignacionRol
    {
        $area = AreaTi::create([
            'ministerio_id' => $this->crearMinisterio()->id,
            'codigo' => 'TI-'.random_int(1000, 9999),
            'nombre' => 'Dirección de Pruebas',
        ]);
        $rol = TipoRol::create(['codigo' => 'ROL'.random_int(1000, 9999), 'nombre' => 'Rol de Prueba']);

        return AsignacionRol::create(array_merge([
            'persona_id' => $this->crearPersona()->id,
            'area_ti_id' => $area->id,
            'tipo_rol_id' => $rol->id,
            'fecha_inicio' => '2024-03-01',
            'observaciones' => 'Designación inicial',
        ], $atributos));
    }

    public function test_finaliza_con_fecha_motivo_y_acto(): void
    {
        $this->actuarComo('EDITOR');
        $asignacion = $this->crearAsignacion();

        $this->postJson("/api/asignaciones-rol/{$asignacion->id}/finalizar-vigencia", [
            'fecha_fin' => '2026-10-24', 'motivo' => 'Renuncia', 'acto_administrativo' => 'RESOL-2026-15',
        ])->assertOk();

        $asignacion->refresh();
        $this->assertSame('2026-10-24', $asignacion->fecha_fin->toDateString());
        $this->assertSame("Designación inicial\nBaja de cargo · Renuncia · Acto: RESOL-2026-15", $asignacion->observaciones);
        $this->assertSame('HISTORICO', $asignacion->estado_vigencia);
    }

    public function test_sin_motivo_ni_acto_registra_solo_la_baja(): void
    {
        $this->actuarComo('EDITOR');
        $asignacion = $this->crearAsignacion(['observaciones' => null]);

        $this->postJson("/api/asignaciones-rol/{$asignacion->id}/finalizar-vigencia", ['fecha_fin' => '2026-10-25'])
            ->assertOk();

        $this->assertSame('Baja de cargo', $asignacion->refresh()->observaciones);
    }

    public function test_rechaza_una_fecha_anterior_al_inicio_del_cargo(): void
    {
        $this->actuarComo('EDITOR');
        $asignacion = $this->crearAsignacion();

        $this->postJson("/api/asignaciones-rol/{$asignacion->id}/finalizar-vigencia", ['fecha_fin' => '2024-02-28'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('fecha_fin');

        $this->assertNull($asignacion->refresh()->fecha_fin);
    }

    public function test_lector_no_puede_finalizar(): void
    {
        $this->actuarComo('LECTOR');
        $asignacion = $this->crearAsignacion();

        $this->postJson("/api/asignaciones-rol/{$asignacion->id}/finalizar-vigencia", ['fecha_fin' => '2026-10-25'])
            ->assertForbidden();
    }
}
