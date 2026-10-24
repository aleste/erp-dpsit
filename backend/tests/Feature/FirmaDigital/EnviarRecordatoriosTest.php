<?php

namespace Tests\Feature\FirmaDigital;

use App\Mail\AvisoVencimientoArea;
use App\Mail\RecordatorioVencimientoCertificado;
use App\Models\CertificadoFirma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class EnviarRecordatoriosTest extends TestCase
{
    use FirmaDigitalTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-23 08:00:00');
    }

    public function test_envia_el_recordatorio_una_sola_vez(): void
    {
        Mail::fake();
        $persona = $this->crearPersona(['email_principal' => 'titular@test.local']);
        $certificado = $this->crearEntregaConCertificado($persona, '2026-11-20', '2026-10-21');

        $this->artisan('firma-digital:enviar-recordatorios')->assertSuccessful();
        $this->artisan('firma-digital:enviar-recordatorios')->assertSuccessful();

        Mail::assertSent(RecordatorioVencimientoCertificado::class, 1);
        Mail::assertSent(RecordatorioVencimientoCertificado::class, fn ($mail) => $mail->hasTo('titular@test.local'));

        $certificado->refresh();
        $this->assertSame(CertificadoFirma::RECORDATORIO_ENVIADO, $certificado->recordatorio_estado);
        $this->assertNotNull($certificado->recordatorio_enviado_at);
        $this->assertSame('titular@test.local', $certificado->recordatorio_email);
    }

    public function test_no_envia_antes_de_la_fecha_de_recordatorio(): void
    {
        Mail::fake();
        $this->crearEntregaConCertificado($this->crearPersona(), '2027-06-30', '2027-05-31');

        $this->artisan('firma-digital:enviar-recordatorios')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_sin_email_valido_marca_el_caso_y_continua_con_los_demas(): void
    {
        Mail::fake();
        $sinEmail = $this->crearEntregaConCertificado($this->crearPersona(['email_principal' => null]), '2026-11-20', '2026-10-21');
        $conEmail = $this->crearEntregaConCertificado($this->crearPersona(), '2026-11-21', '2026-10-22');

        $this->artisan('firma-digital:enviar-recordatorios')->assertSuccessful();

        $this->assertSame(CertificadoFirma::RECORDATORIO_SIN_EMAIL, $sinEmail->fresh()->recordatorio_estado);
        $this->assertSame(CertificadoFirma::RECORDATORIO_ENVIADO, $conEmail->fresh()->recordatorio_estado);
        Mail::assertSent(RecordatorioVencimientoCertificado::class, 1);
    }

    public function test_usa_el_email_alternativo_si_el_principal_no_existe(): void
    {
        Mail::fake();
        $persona = $this->crearPersona(['email_principal' => null, 'email_alternativo' => 'alternativo@test.local']);
        $this->crearEntregaConCertificado($persona, '2026-11-20', '2026-10-21');

        $this->artisan('firma-digital:enviar-recordatorios')->assertSuccessful();

        Mail::assertSent(RecordatorioVencimientoCertificado::class, fn ($mail) => $mail->hasTo('alternativo@test.local'));
    }

    public function test_envia_un_aviso_separado_al_area_ademas_del_recordatorio(): void
    {
        Mail::fake();
        config(['firma_digital.aviso_area_emails' => ['tokens@test.local', 'soporte@test.local']]);
        $this->crearEntregaConCertificado($this->crearPersona(['email_principal' => 'titular@test.local']), '2026-11-20', '2026-10-21');

        $this->artisan('firma-digital:enviar-recordatorios')->assertSuccessful();
        $this->artisan('firma-digital:enviar-recordatorios')->assertSuccessful();

        Mail::assertSent(RecordatorioVencimientoCertificado::class, fn ($mail) => $mail->hasTo('titular@test.local') && $mail->cc === []);
        Mail::assertSent(AvisoVencimientoArea::class, 1);
        Mail::assertSent(AvisoVencimientoArea::class, fn ($mail) => $mail->hasTo('tokens@test.local')
            && $mail->hasTo('soporte@test.local')
            && ! $mail->hasTo('titular@test.local')
            && $mail->emailTitular === 'titular@test.local');
    }

    public function test_avisa_al_area_si_el_titular_no_tiene_email(): void
    {
        Mail::fake();
        config(['firma_digital.aviso_area_emails' => ['tokens@test.local']]);
        $certificado = $this->crearEntregaConCertificado($this->crearPersona(['email_principal' => null]), '2026-11-20', '2026-10-21');

        $this->artisan('firma-digital:enviar-recordatorios')->assertSuccessful();

        Mail::assertNotSent(RecordatorioVencimientoCertificado::class);
        Mail::assertSent(AvisoVencimientoArea::class, fn ($mail) => $mail->hasTo('tokens@test.local') && $mail->emailTitular === null);
        $this->assertSame(CertificadoFirma::RECORDATORIO_SIN_EMAIL, $certificado->fresh()->recordatorio_estado);
    }

    public function test_sin_buzon_configurado_no_avisa_al_area(): void
    {
        Mail::fake();
        $this->crearEntregaConCertificado($this->crearPersona(['email_principal' => 'titular@test.local']), '2026-11-20', '2026-10-21');

        $this->artisan('firma-digital:enviar-recordatorios')->assertSuccessful();

        Mail::assertSent(RecordatorioVencimientoCertificado::class, 1);
        Mail::assertNotSent(AvisoVencimientoArea::class);
    }

    public function test_el_aviso_al_area_incluye_los_datos_de_gestion(): void
    {
        $persona = $this->crearPersona(['apellido' => 'Titular', 'nombre' => 'Ana', 'email_principal' => null, 'telefono_principal' => '0221-555-0000']);
        $certificado = $this->crearEntregaConCertificado($persona, '2026-11-20', '2026-10-21');

        $html = (new AvisoVencimientoArea($certificado->load('entrega.persona', 'entrega.dispositivo'), null))->render();

        $this->assertStringContainsString('Titular, Ana', $html);
        $this->assertStringContainsString('0221-555-0000', $html);
        $this->assertStringContainsString('20/11/2026', $html);
        $this->assertStringContainsString('No se pudo notificar al titular', $html);
    }

    public function test_cancela_el_recordatorio_de_un_certificado_ya_vencido(): void
    {
        Mail::fake();
        $certificado = $this->crearEntregaConCertificado($this->crearPersona(), '2026-10-01', '2026-09-01');

        $this->artisan('firma-digital:enviar-recordatorios')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertSame(CertificadoFirma::RECORDATORIO_CANCELADO, $certificado->fresh()->recordatorio_estado);
    }

    public function test_registra_el_error_y_reintenta_hasta_el_maximo(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP no disponible'));
        $certificado = $this->crearEntregaConCertificado($this->crearPersona(), '2026-11-20', '2026-10-21');

        for ($i = 0; $i < 5; $i++) {
            $this->artisan('firma-digital:enviar-recordatorios')->assertSuccessful();
        }

        $certificado->refresh();
        $this->assertSame(CertificadoFirma::RECORDATORIO_ERROR, $certificado->recordatorio_estado);
        $this->assertSame(3, $certificado->recordatorio_intentos);
        $this->assertStringContainsString('SMTP no disponible', $certificado->recordatorio_ultimo_error);
    }

    public function test_dry_run_no_envia_ni_modifica(): void
    {
        Mail::fake();
        $certificado = $this->crearEntregaConCertificado($this->crearPersona(), '2026-11-20', '2026-10-21');

        $this->artisan('firma-digital:enviar-recordatorios --dry-run')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertSame(CertificadoFirma::RECORDATORIO_PENDIENTE, $certificado->fresh()->recordatorio_estado);
    }

    public function test_el_comando_esta_programado_diariamente(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('firma-digital:enviar-recordatorios')->assertSuccessful();
    }
}
