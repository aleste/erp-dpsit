<?php

namespace Tests\Feature\Expedientes;

use App\Models\Expediente;
use App\Models\ExpedienteIntervencion;
use App\Models\ExpedienteMovimiento;
use App\Models\Ministerio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportacionPlanillaTest extends TestCase
{
    use ExpedientesTestHelpers, RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-23 10:00:00');
        $this->sembrarCatalogos();
        $this->dir = storage_path('framework/testing/importacion_'.uniqid());
        @mkdir($this->dir, 0775, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("{$this->dir}/*") ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function archivo(string $nombre, array $filas): string
    {
        $ruta = "{$this->dir}/{$nombre}";
        $h = fopen($ruta, 'w');
        foreach ($filas as $f) {
            fputcsv($h, $f, ';');
        }
        fclose($h);

        return $ruta;
    }

    private function planilla(): string
    {
        $enc = ['Ingreso', 'Egreso', 'Nº Expediente Electrónico', 'Organismo', 'Descripción', 'EE analizado por:', 'Requerimiento',
            'Estado', 'ETB', 'A revisión', 'Envíado a:', 'Firma', 'Remitido a:', 'Observaciones', 'C/ acto administrativo'];

        return $this->archivo('hoja.csv', [
            $enc,
            // Fila 10: 1ra intervención, salida con GEDO y destino
            ['2026-03-02', '2026-03-10', 'EX-2026-00000001- -GDEBA-ORGA', 'Organismo de Prueba - ODP', 'Solicitud A', 'Ana, Beto', 'Nomenclador Informático', 'SALIDA de EE', '', '2026-03-08', 'Ana', '', 'Dirección de Personal  ', 'IF-2026-11111111-GDEBA-DPSITMGGP', 'Si'],
            // Fila 11: 2da intervención del mismo EE, en análisis (abierta)
            ['2026-09-01', '', ' EX-2026-00000001- -GDEBA-ORGA', 'Organismo de Prueba - ODP', 'Solicitud A (2da intervención)', 'Ana', 'Nomenclador Informático', 'EE EN ANÁLISIS', '', '', '', '', '', '', ''],
            // Fila 12: salida sin fecha de egreso, año mal tipeado, responsable sin equivalencia
            ['02/06/0206', '', 'EX-2026-00000002- -GDEBA-ORGB', 'Organismo Inexistente', 'Solicitud B', 'Carla', 'Servidores', 'SALIDA de EE', '', '', '', '', 'dirección de personal', 'PV-2026-22222222-GDEBA-DPSITMGGP observado', ''],
            // Fila 13: egreso anterior al ingreso (queda vacío)
            ['2026-05-10', '2025-05-01', '5900-353/2026', 'Organismo de Prueba - ODP', 'Solicitud C', 'Beto', 'UPS', 'SALIDA de EE', '', '', '', '', 'Mesa de Entradas', '', ''],
            // Fila 14: duplicada de la fila 10
            ['2026-03-02', '2026-03-10', 'EX-2026-00000001- -GDEBA-ORGA', 'Organismo de Prueba - ODP', 'Solicitud A', 'Ana', 'Nomenclador Informático', 'SALIDA de EE', '', '', '', '', 'Dirección de Personal', '', ''],
            // Fila 15: sin número
            ['2026-04-01', '', '', 'Organismo de Prueba - ODP', 'Sin número', '', '', '', '', '', '', '', '', '', ''],
        ]);
    }

    private function importar(bool $simulacion): void
    {
        $ana = $this->usuario('LECTOR', 'OPERADOR', 'Ana');
        $beto = $this->usuario('LECTOR', 'OPERADOR', 'Beto');
        $equivalencias = $this->archivo('equivalencias.csv', [['nombre', 'email'], ['Ana', $ana->email], ['beto', $beto->email]]);
        $organismos = $this->archivo('organismos.csv', [['ORGANISMO'], ['Organismo de Prueba - ODP']]);

        $this->artisan('expedientes:importar', array_filter([
            'archivo' => $this->planilla(),
            '--organismos' => $organismos,
            '--equivalencias' => $equivalencias,
            '--hoja' => '2026',
            '--dry-run' => $simulacion,
        ]))->assertSuccessful();
    }

    public function test_la_simulacion_no_escribe_en_la_base(): void
    {
        $this->importar(true);

        $this->assertSame(0, Expediente::count());
        $this->assertSame(0, Ministerio::count());
    }

    public function test_importa_intervenciones_sin_inventar_datos(): void
    {
        $this->importar(false);

        // 3 expedientes, 4 intervenciones (la duplicada y la fila sin número se omiten)
        $this->assertSame(3, Expediente::count());
        $this->assertSame(4, ExpedienteIntervencion::count());
        $this->assertSame(1, Ministerio::where('sigla', 'ODP')->count());

        $ex1 = Expediente::where('numero', 'EX-2026-00000001- -GDEBA-ORGA')->firstOrFail();
        [$primera, $segunda] = $ex1->intervenciones()->orderBy('numero_intervencion')->get()->all();
        $this->assertSame([1, 2], [$primera->numero_intervencion, $segunda->numero_intervencion]);
        $this->assertFalse($primera->en_area);
        $this->assertTrue($segunda->en_area);
        $this->assertSame('IF-2026-11111111-GDEBA-DPSITMGGP', $primera->documento_respuesta);
        $this->assertSame('Ana', $primera->responsable->name);
        $this->assertSame(['Beto'], $primera->colaboradores->pluck('name')->all());
        $this->assertSame('SI', $primera->con_acto_administrativo);
        $this->assertSame(['INGRESO', 'ENVIO_REVISION', 'EGRESO'], $primera->movimientos()->orderBy('id')->pluck('tipo')->all());
        $this->assertTrue($primera->movimientos()->first()->importado);

        // Año mal tipeado corregido con constancia; salida sin fecha de egreso, sin movimiento EGRESO inventado
        $ex2 = Expediente::where('numero', 'EX-2026-00000002- -GDEBA-ORGB')->firstOrFail()->intervenciones()->first();
        $this->assertSame('2026-06-02', $ex2->fecha_ingreso->toDateString());
        $this->assertFalse($ex2->en_area);
        $this->assertNull($ex2->fecha_egreso);
        $this->assertNull($ex2->responsable_id);
        $this->assertSame('PV-2026-22222222-GDEBA-DPSITMGGP', $ex2->documento_respuesta);
        $this->assertStringContainsString('observado', $ex2->observaciones);
        $this->assertStringContainsString('original: 02/06/0206', $ex2->observaciones);
        $this->assertStringContainsString('Analizado por (planilla): Carla', $ex2->observaciones);
        $this->assertSame(['INGRESO'], $ex2->movimientos()->pluck('tipo')->all());
        // «Dirección de Personal» y «dirección de personal» = un solo destino
        $this->assertSame($ex2->dependencia_destino_id, $primera->dependencia_destino_id);

        // Egreso anterior al ingreso: queda vacío con el valor original
        $ex3 = Expediente::where('formato', 'OTRO')->firstOrFail()->intervenciones()->first();
        $this->assertNull($ex3->fecha_egreso);
        $this->assertStringContainsString('2025-05-01', $ex3->observaciones);

        $this->assertSame(0, ExpedienteMovimiento::whereNotNull('user_id')->count());
    }

    public function test_acepta_encabezados_con_variantes_de_escritura(): void
    {
        $ruta = $this->archivo('variante.csv', [
            ['Ingreso', 'Egreso', 'N° Expediente Electronico', 'Organismo', 'Descripcion', 'EE analizado por:', 'Requerimiento', 'Estado'],
            ['2026-10-01', '', 'EX-2026-00000009- -GDEBA-ORGZ', '', 'Solicitud Z', '', '', 'FRENADO'],
        ]);

        $this->artisan('expedientes:importar', ['archivo' => $ruta])->assertSuccessful();

        $intervencion = ExpedienteIntervencion::firstOrFail();
        $this->assertSame('FRENADO', $intervencion->estado->nombre);
        $this->assertTrue($intervencion->en_area);
        $this->assertSame('Solicitud Z', $intervencion->descripcion);
    }

    public function test_reimportar_no_duplica(): void
    {
        $this->importar(false);
        $this->artisan('expedientes:importar', ['archivo' => "{$this->dir}/hoja.csv", '--hoja' => '2026'])->assertSuccessful();

        $this->assertSame(4, ExpedienteIntervencion::count());
    }
}
