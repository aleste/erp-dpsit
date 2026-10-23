<?php

namespace Tests\Feature\Expedientes;

use App\Models\DependenciaDestino;
use App\Models\Expediente;
use App\Models\ExpedienteIntervencion;
use App\Models\ExpedienteMovimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class CircuitoExpedienteTest extends TestCase
{
    use ExpedientesTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-23 10:00:00');
        $this->sembrarCatalogos();
    }

    private function egresar(int $id, array $datos = [])
    {
        return $this->postJson("/api/expedientes/intervenciones/{$id}/egreso", array_merge([
            'fecha_egreso' => '2026-10-10',
            'estado_id' => $this->estado('SALIDA de EE')->id,
            'dependencia_destino_nombre' => 'Dirección Provincial de Personal',
            'documento_respuesta' => 'IF-2026-11111111-GDEBA-DPSITMGGP',
        ], $datos));
    }

    public function test_el_ingreso_normaliza_el_numero_y_registra_el_historial(): void
    {
        $operador = $this->actuarComo($this->usuario('LECTOR', 'OPERADOR'));

        $intervencion = $this->ingresar([
            'numero' => '  ex-2026-12345678-  -gdeba-dptest ',
            'responsable_id' => $operador->id,
        ]);

        $expediente = Expediente::first();
        $this->assertSame('EX-2026-12345678- -GDEBA-DPTEST', $expediente->numero);
        $this->assertSame('GDE', $expediente->formato);
        $this->assertSame(2026, $expediente->anio);
        $this->assertSame('DPTEST', $expediente->reparticion);
        $this->assertSame(1, $intervencion['numero_intervencion']);
        $this->assertSame('EE EN ANÁLISIS', $intervencion['estado']['nombre']);
        $this->assertSame(['INGRESO', 'ASIGNACION'], ExpedienteMovimiento::orderBy('id')->pluck('tipo')->all());
        $this->assertSame($operador->id, ExpedienteMovimiento::first()->user_id);
    }

    public function test_detecta_un_expediente_que_ya_esta_en_el_area(): void
    {
        $this->actuarComo($this->usuario('LECTOR', 'OPERADOR'));
        $this->ingresar();

        $this->getJson('/api/expedientes/buscar-numero?numero=EX2026-12345678GDEBADPTEST')
            ->assertJsonPath('data.existe', true)
            ->assertJsonPath('data.en_area', true);

        $this->postJson('/api/expedientes', ['numero' => 'EX-2026-12345678- -GDEBA-DPTEST', 'fecha_ingreso' => '2026-10-05', 'descripcion' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('numero');
    }

    public function test_el_egreso_exige_destino_y_documento_y_cierra_la_intervencion(): void
    {
        $this->actuarComo($this->usuario('LECTOR', 'OPERADOR'));
        $id = $this->ingresar()['id'];

        $this->egresar($id, ['documento_respuesta' => null])->assertStatus(422)->assertJsonValidationErrors('documento_respuesta');
        $this->egresar($id, ['dependencia_destino_nombre' => null])->assertStatus(422);
        $this->egresar($id, ['fecha_egreso' => '2026-09-01'])->assertStatus(422)->assertJsonValidationErrors('fecha_egreso');

        $this->egresar($id)->assertOk()->assertJsonPath('data.en_area', false);

        $intervencion = ExpedienteIntervencion::find($id);
        $this->assertSame('2026-10-10', $intervencion->fecha_egreso->toDateString());
        $this->assertSame('Dirección Provincial de Personal', $intervencion->dependenciaDestino->nombre);
        $this->assertSame(1, DependenciaDestino::count());
        $this->assertSame('EGRESO', $intervencion->movimientos()->latest('id')->first()->tipo);

        // Ya egresada: no admite más cambios de circuito
        $this->postJson("/api/expedientes/intervenciones/{$id}/estado", ['estado_id' => $this->estado('FRENADO')->id])->assertStatus(422);
    }

    public function test_devolver_con_pase_simple_permite_egresar_sin_documento(): void
    {
        $this->actuarComo($this->usuario('LECTOR', 'OPERADOR'));
        $id = $this->ingresar()['id'];

        $this->postJson("/api/expedientes/intervenciones/{$id}/estado", ['estado_id' => $this->estado('PARA DEVOLVER CON PASE SIMPLE')->id])->assertOk();
        $this->egresar($id, ['documento_respuesta' => null])->assertOk();
    }

    public function test_el_reingreso_abre_una_nueva_intervencion_y_conserva_la_anterior(): void
    {
        $this->actuarComo($this->usuario('LECTOR', 'OPERADOR'));
        $primera = $this->ingresar()['id'];
        $this->egresar($primera)->assertOk();

        $this->postJson('/api/expedientes', ['numero' => 'EX-2026-12345678- -GDEBA-DPTEST', 'fecha_ingreso' => '2026-10-15'])
            ->assertStatus(422)->assertJsonValidationErrors('descripcion');

        $segunda = $this->ingresar(['fecha_ingreso' => '2026-10-15', 'descripcion' => 'Vuelve con documentación'])['numero_intervencion'];

        $this->assertSame(2, $segunda);
        $this->assertSame(1, Expediente::count());
        $this->assertFalse(ExpedienteIntervencion::find($primera)->en_area);
        $this->assertSame(2, Expediente::first()->intervencionActual->numero_intervencion);
    }

    public function test_el_cambio_de_estado_registra_estado_anterior_y_nuevo(): void
    {
        $this->actuarComo($this->usuario('LECTOR', 'OPERADOR'));
        $id = $this->ingresar()['id'];
        $frenado = $this->estado('FRENADO');

        $this->postJson("/api/expedientes/intervenciones/{$id}/estado", ['estado_id' => $frenado->id, 'descripcion' => 'Falta documentación'])->assertOk();
        $this->postJson("/api/expedientes/intervenciones/{$id}/estado", ['estado_id' => $this->estado('SALIDA de EE')->id])
            ->assertStatus(422)->assertJsonValidationErrors('estado_id');

        $mov = ExpedienteMovimiento::where('tipo', 'CAMBIO_ESTADO')->first();
        $this->assertSame($this->estado('EE EN ANÁLISIS')->id, $mov->estado_anterior_id);
        $this->assertSame($frenado->id, $mov->estado_nuevo_id);
    }

    public function test_reasignar_a_otra_persona_requiere_supervisor(): void
    {
        $operadorA = $this->usuario('LECTOR', 'OPERADOR', 'Operador A');
        $operadorB = $this->usuario('LECTOR', 'OPERADOR', 'Operador B');
        $sinAcceso = $this->usuario('LECTOR');

        $this->actuarComo($operadorA);
        $id = $this->ingresar()['id'];

        // Tomar uno sin responsable: cualquier operador
        $this->postJson("/api/expedientes/intervenciones/{$id}/asignar", ['responsable_id' => $operadorA->id])->assertOk();
        // Reasignar a otra persona: supervisor
        $this->actuarComo($operadorB);
        $this->postJson("/api/expedientes/intervenciones/{$id}/asignar", ['responsable_id' => $operadorB->id])->assertStatus(403);

        $this->actuarComo($this->usuario('LECTOR', 'SUPERVISOR'));
        $this->postJson("/api/expedientes/intervenciones/{$id}/asignar", ['responsable_id' => $sinAcceso->id])
            ->assertStatus(422)->assertJsonValidationErrors('responsable_id');
        $this->postJson("/api/expedientes/intervenciones/{$id}/asignar", ['responsable_id' => $operadorB->id, 'colaboradores' => [$operadorA->id]])->assertOk();

        $intervencion = ExpedienteIntervencion::with('colaboradores')->find($id);
        $this->assertSame($operadorB->id, $intervencion->responsable_id);
        $this->assertSame([$operadorA->id], $intervencion->colaboradores->pluck('id')->all());
    }

    public function test_reabrir_y_corregir_fechas_quedan_registrados_con_motivo(): void
    {
        $this->actuarComo($this->usuario('LECTOR', 'SUPERVISOR'));
        $id = $this->ingresar()['id'];
        $this->egresar($id)->assertOk();

        $this->postJson("/api/expedientes/intervenciones/{$id}/corregir-fechas", ['motivo' => 'Error de carga', 'fecha_egreso' => '2026-09-01'])
            ->assertStatus(422);
        $this->postJson("/api/expedientes/intervenciones/{$id}/corregir-fechas", ['motivo' => 'Error de carga', 'fecha_egreso' => '2026-10-12'])
            ->assertOk();

        $this->postJson("/api/expedientes/intervenciones/{$id}/reabrir", ['motivo' => 'Se egresó por error', 'estado_id' => $this->estado('EE EN ANÁLISIS')->id])
            ->assertOk()->assertJsonPath('data.en_area', true);

        $tipos = ExpedienteMovimiento::where('intervencion_id', $id)->orderBy('id')->pluck('tipo')->all();
        $this->assertSame(['INGRESO', 'EGRESO', 'CORRECCION', 'REAPERTURA'], $tipos);
        $this->assertStringContainsString('Error de carga', ExpedienteMovimiento::where('tipo', 'CORRECCION')->first()->descripcion);
    }

    public function test_los_movimientos_no_se_modifican_ni_se_borran(): void
    {
        $this->actuarComo($this->usuario('LECTOR', 'OPERADOR'));
        $this->ingresar();
        $movimiento = ExpedienteMovimiento::first();

        $this->expectException(LogicException::class);
        $movimiento->update(['descripcion' => 'alterado']);
    }

    public function test_filtros_y_tablero(): void
    {
        $yo = $this->actuarComo($this->usuario('LECTOR', 'OPERADOR'));
        $this->ingresar(['numero' => 'EX-2026-00000001- -GDEBA-A', 'fecha_ingreso' => '2026-08-01', 'responsable_id' => $yo->id]);
        $this->ingresar(['numero' => 'EX-2026-00000002- -GDEBA-B', 'fecha_ingreso' => '2026-10-20']);
        $tercera = $this->ingresar(['numero' => '5900-353/2026', 'fecha_ingreso' => '2026-10-01'])['id'];
        $this->egresar($tercera)->assertOk();

        $this->getJson('/api/expedientes')->assertJsonPath('data.total', 2);
        $this->getJson('/api/expedientes?situacion=TODAS')->assertJsonPath('data.total', 3);
        $this->getJson('/api/expedientes?responsable_id=sin')->assertJsonPath('data.total', 1);
        $this->getJson('/api/expedientes?mis=1')->assertJsonPath('data.total', 1);
        $this->getJson('/api/expedientes?q=2026000000%2002')->assertJsonPath('data.total', 1);
        $this->getJson('/api/expedientes?situacion=TODAS&q=5900353')->assertJsonPath('data.data.0.expediente.formato', 'OTRO');
        $this->getJson('/api/expedientes?dias_min=30')->assertJsonPath('data.total', 1);

        $this->getJson('/api/expedientes/tablero')
            ->assertJsonPath('data.totales.en_area', 2)
            ->assertJsonPath('data.totales.sin_responsable', 1)
            ->assertJsonPath('data.totales.mis_expedientes', 1)
            ->assertJsonPath('data.totales.demorados', 1)
            ->assertJsonPath('data.totales.egresos_mes', 1);
    }

    public function test_la_baja_logica_oculta_el_expediente_y_conserva_el_historial(): void
    {
        $this->actuarComo($this->usuario('ADMINISTRADOR'));
        $intervencion = $this->ingresar();

        $this->deleteJson("/api/expedientes/{$intervencion['expediente_id']}")->assertStatus(422)->assertJsonValidationErrors('motivo');
        $this->deleteJson("/api/expedientes/{$intervencion['expediente_id']}", ['motivo' => 'Cargado por error'])->assertOk();

        $this->getJson('/api/expedientes?situacion=TODAS')->assertJsonPath('data.total', 0);
        $this->getJson('/api/expedientes/tablero')->assertJsonPath('data.totales.en_area', 0);
        $this->assertSame(1, ExpedienteMovimiento::count());
        $this->assertSame(0, ExpedienteIntervencion::count());

        // El mismo número puede volver a ingresar: se restaura el expediente y se abre una nueva intervención
        $nueva = $this->ingresar(['fecha_ingreso' => '2026-10-20', 'descripcion' => 'Ingreso correcto']);
        $this->assertSame(2, $nueva['numero_intervencion']);
        $this->assertSame(1, Expediente::count());
    }

    public function test_exporta_csv_con_los_filtros(): void
    {
        $this->actuarComo($this->usuario('LECTOR', 'CONSULTA'));

        $respuesta = $this->get('/api/expedientes/exportar?situacion=TODAS');
        $respuesta->assertOk();
        $this->assertStringContainsString('Nº Expediente', $respuesta->streamedContent());
    }
}
