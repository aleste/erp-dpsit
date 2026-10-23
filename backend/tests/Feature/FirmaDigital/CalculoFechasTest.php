<?php

namespace Tests\Feature\FirmaDigital;

use App\Services\FirmaDigitalService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CalculoFechasTest extends TestCase
{
    public function test_calcula_vencimiento_a_un_anio_y_recordatorio_treinta_dias_antes(): void
    {
        $fechas = app(FirmaDigitalService::class)->resolverFechas(['fecha_inicio' => '2026-03-15']);

        $this->assertSame('2026-03-15', $fechas['fecha_inicio']);
        $this->assertSame('2027-03-15', $fechas['fecha_vencimiento']);
        $this->assertSame('2027-02-13', $fechas['fecha_recordatorio']);
        $this->assertFalse($fechas['vencimiento_manual']);
        $this->assertFalse($fechas['recordatorio_manual']);
    }

    public function test_la_anticipacion_y_la_vigencia_son_configurables(): void
    {
        config(['firma_digital.vigencia_meses' => 24, 'firma_digital.recordatorio_dias_anticipacion' => 15]);

        $fechas = app(FirmaDigitalService::class)->resolverFechas(['fecha_inicio' => '2026-01-31']);

        $this->assertSame('2028-01-31', $fechas['fecha_vencimiento']);
        $this->assertSame('2028-01-16', $fechas['fecha_recordatorio']);
    }

    public function test_respeta_fechas_manuales(): void
    {
        $fechas = app(FirmaDigitalService::class)->resolverFechas([
            'fecha_inicio' => '2026-01-10',
            'vencimiento_manual' => true,
            'fecha_vencimiento' => '2026-08-01',
            'recordatorio_manual' => true,
            'fecha_recordatorio' => '2026-07-20',
        ]);

        $this->assertSame('2026-08-01', $fechas['fecha_vencimiento']);
        $this->assertSame('2026-07-20', $fechas['fecha_recordatorio']);
        $this->assertTrue($fechas['vencimiento_manual']);
        $this->assertTrue($fechas['recordatorio_manual']);
    }

    public function test_con_vencimiento_manual_el_recordatorio_se_calcula_desde_ese_vencimiento(): void
    {
        $fechas = app(FirmaDigitalService::class)->resolverFechas([
            'fecha_inicio' => '2026-01-10',
            'vencimiento_manual' => true,
            'fecha_vencimiento' => '2026-08-01',
        ]);

        $this->assertSame('2026-07-02', $fechas['fecha_recordatorio']);
    }

    public function test_rechaza_vencimiento_anterior_al_inicio(): void
    {
        $this->expectException(ValidationException::class);

        app(FirmaDigitalService::class)->resolverFechas([
            'fecha_inicio' => '2026-05-01',
            'vencimiento_manual' => true,
            'fecha_vencimiento' => '2026-04-01',
        ]);
    }
}
