<?php

namespace Tests\Feature\AreasTi;

use App\Models\AreaTi;
use App\Models\AsignacionRol;
use App\Models\TipoRol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\FirmaDigital\FirmaDigitalTestHelpers;
use Tests\TestCase;

/**
 * Máxima autoridad de un área TI con las reglas de D-32 (DEF-09)
 */
class AutoridadAreaTest extends TestCase
{
    use FirmaDigitalTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-25 10:00:00');
    }

    private function crearArea(): AreaTi
    {
        return AreaTi::create([
            'ministerio_id' => $this->crearMinisterio()->id,
            'codigo' => 'TI-'.random_int(1000, 9999),
            'nombre' => 'Dirección de Pruebas',
        ]);
    }

    private function crearTipoRol(bool $autoridadPorDefecto = false): TipoRol
    {
        return TipoRol::create([
            'codigo' => 'ROL'.random_int(1000, 9999),
            'nombre' => 'Rol de Prueba',
            'es_maxima_autoridad_default' => $autoridadPorDefecto,
        ]);
    }

    private function autoridadesVigentes(AreaTi $area): int
    {
        return AsignacionRol::where('area_ti_id', $area->id)->where('es_maxima_autoridad', true)->vigente()->count();
    }

    public function test_designar_cierra_la_autoridad_vigente_el_dia_anterior(): void
    {
        $this->actuarComo('EDITOR');
        $area = $this->crearArea();
        $rol = $this->crearTipoRol();
        $anterior = $this->crearPersona(['apellido' => 'Anterior']);
        $nueva = $this->crearPersona(['apellido' => 'Nueva']);

        $this->postJson("/api/areas-ti/{$area->id}/maxima-autoridad", [
            'persona_id' => $anterior->id, 'tipo_rol_id' => $rol->id, 'fecha_inicio' => '2023-12-11',
        ])->assertOk();

        $this->postJson("/api/areas-ti/{$area->id}/maxima-autoridad", [
            'persona_id' => $nueva->id, 'tipo_rol_id' => $rol->id, 'fecha_inicio' => '2026-10-20',
        ])->assertOk();

        $previa = AsignacionRol::where('persona_id', $anterior->id)->first();
        $this->assertSame('2026-10-19', $previa->fecha_fin->toDateString());
        $this->assertStringContainsString('Cese por nueva designación', $previa->observaciones);
        $this->assertSame(1, $this->autoridadesVigentes($area));
        $this->assertSame($nueva->id, $area->maximaAutoridadVigente()->value('persona_id'));
    }

    public function test_cierra_tambien_una_autoridad_con_cese_futuro(): void
    {
        $this->actuarComo('EDITOR');
        $area = $this->crearArea();
        $rol = $this->crearTipoRol();
        AsignacionRol::create([
            'persona_id' => $this->crearPersona()->id, 'area_ti_id' => $area->id, 'tipo_rol_id' => $rol->id,
            'fecha_inicio' => '2024-01-01', 'fecha_fin' => '2027-12-10', 'es_maxima_autoridad' => true,
        ]);

        $this->postJson("/api/areas-ti/{$area->id}/maxima-autoridad", [
            'persona_id' => $this->crearPersona()->id, 'tipo_rol_id' => $rol->id, 'fecha_inicio' => '2026-10-25',
        ])->assertOk();

        $this->assertSame(1, $this->autoridadesVigentes($area));
    }

    public function test_rechaza_a_la_misma_persona_y_una_fecha_anterior_a_la_vigente(): void
    {
        $this->actuarComo('EDITOR');
        $area = $this->crearArea();
        $rol = $this->crearTipoRol();
        $persona = $this->crearPersona();

        $this->postJson("/api/areas-ti/{$area->id}/maxima-autoridad", [
            'persona_id' => $persona->id, 'tipo_rol_id' => $rol->id, 'fecha_inicio' => '2026-01-01',
        ])->assertOk();

        $this->postJson("/api/areas-ti/{$area->id}/maxima-autoridad", [
            'persona_id' => $persona->id, 'tipo_rol_id' => $rol->id, 'fecha_inicio' => '2026-10-25',
        ])->assertStatus(422)->assertJsonValidationErrors('persona_id');

        $this->postJson("/api/areas-ti/{$area->id}/maxima-autoridad", [
            'persona_id' => $this->crearPersona()->id, 'tipo_rol_id' => $rol->id, 'fecha_inicio' => '2025-06-01',
        ])->assertStatus(422)->assertJsonValidationErrors('fecha_inicio');

        $this->assertSame(1, $this->autoridadesVigentes($area));
    }

    public function test_la_casilla_de_maxima_autoridad_en_una_asignacion_tambien_cierra_la_anterior(): void
    {
        $this->actuarComo('EDITOR');
        $area = $this->crearArea();
        $rol = $this->crearTipoRol();
        $anterior = $this->crearPersona();

        $this->postJson('/api/asignaciones-rol', [
            'persona_id' => $anterior->id, 'area_ti_id' => $area->id, 'tipo_rol_id' => $rol->id,
            'fecha_inicio' => '2024-03-01', 'es_maxima_autoridad' => true,
        ])->assertCreated();

        $this->postJson('/api/asignaciones-rol', [
            'persona_id' => $this->crearPersona()->id, 'area_ti_id' => $area->id, 'tipo_rol_id' => $rol->id,
            'fecha_inicio' => '2026-10-25', 'es_maxima_autoridad' => true,
        ])->assertCreated();

        $this->assertSame(1, $this->autoridadesVigentes($area));
        $this->assertSame('2026-10-24', AsignacionRol::where('persona_id', $anterior->id)->first()->fecha_fin->toDateString());
    }

    public function test_un_rol_que_es_autoridad_por_defecto_aplica_las_mismas_reglas(): void
    {
        $this->actuarComo('EDITOR');
        $area = $this->crearArea();
        $director = $this->crearTipoRol(autoridadPorDefecto: true);

        foreach (['2024-03-01', '2026-10-25'] as $inicio) {
            $this->postJson('/api/asignaciones-rol', [
                'persona_id' => $this->crearPersona()->id, 'area_ti_id' => $area->id,
                'tipo_rol_id' => $director->id, 'fecha_inicio' => $inicio,
            ])->assertCreated();
        }

        $this->assertSame(1, $this->autoridadesVigentes($area));
    }

    public function test_una_autoridad_historica_o_un_rol_comun_no_afectan_a_la_vigente(): void
    {
        $this->actuarComo('EDITOR');
        $area = $this->crearArea();
        $rol = $this->crearTipoRol();
        $vigente = $this->crearPersona();

        $this->postJson("/api/areas-ti/{$area->id}/maxima-autoridad", [
            'persona_id' => $vigente->id, 'tipo_rol_id' => $rol->id, 'fecha_inicio' => '2025-01-01',
        ])->assertOk();

        // Autoridad histórica cargada con su fecha de fin
        $this->postJson('/api/asignaciones-rol', [
            'persona_id' => $this->crearPersona()->id, 'area_ti_id' => $area->id, 'tipo_rol_id' => $rol->id,
            'fecha_inicio' => '2020-01-01', 'fecha_fin' => '2024-12-31', 'es_maxima_autoridad' => true,
        ])->assertCreated();

        // Rol común en la misma área
        $this->postJson('/api/asignaciones-rol', [
            'persona_id' => $this->crearPersona()->id, 'area_ti_id' => $area->id, 'tipo_rol_id' => $rol->id,
            'fecha_inicio' => '2026-10-25', 'es_maxima_autoridad' => false,
        ])->assertCreated();

        $this->assertSame($vigente->id, $area->maximaAutoridadVigente()->value('persona_id'));
        $this->assertNull(AsignacionRol::where('persona_id', $vigente->id)->first()->fecha_fin);
    }

    public function test_marcar_como_autoridad_al_editar_cierra_la_anterior(): void
    {
        $this->actuarComo('EDITOR');
        $area = $this->crearArea();
        $rol = $this->crearTipoRol();

        $this->postJson("/api/areas-ti/{$area->id}/maxima-autoridad", [
            'persona_id' => $this->crearPersona()->id, 'tipo_rol_id' => $rol->id, 'fecha_inicio' => '2024-01-01',
        ])->assertOk();

        $comun = AsignacionRol::create([
            'persona_id' => $this->crearPersona()->id, 'area_ti_id' => $area->id, 'tipo_rol_id' => $rol->id,
            'fecha_inicio' => '2026-10-25', 'es_maxima_autoridad' => false,
        ]);

        $this->putJson("/api/asignaciones-rol/{$comun->id}", [
            'persona_id' => $comun->persona_id, 'area_ti_id' => $area->id, 'tipo_rol_id' => $rol->id,
            'fecha_inicio' => '2026-10-25', 'es_maxima_autoridad' => true,
        ])->assertOk();

        $this->assertSame(1, $this->autoridadesVigentes($area));
        $this->assertSame($comun->persona_id, $area->maximaAutoridadVigente()->value('persona_id'));
    }

    public function test_lector_no_puede_designar(): void
    {
        $this->actuarComo('LECTOR');
        $area = $this->crearArea();

        $this->postJson("/api/areas-ti/{$area->id}/maxima-autoridad", [
            'persona_id' => $this->crearPersona()->id, 'tipo_rol_id' => $this->crearTipoRol()->id, 'fecha_inicio' => '2026-10-25',
        ])->assertForbidden();
    }
}
