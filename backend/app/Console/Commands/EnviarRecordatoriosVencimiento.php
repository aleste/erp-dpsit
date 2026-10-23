<?php

namespace App\Console\Commands;

use App\Mail\RecordatorioVencimientoCertificado;
use App\Models\CertificadoFirma;
use App\Models\EntregaToken;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envía los recordatorios de vencimiento de certificados de firma digital cuya fecha de recordatorio llegó.
 * Programado diariamente en routes/console.php. Es idempotente: cada certificado se "reclama" con un
 * UPDATE condicional antes de enviar, por lo que ejecuciones sucesivas o simultáneas no duplican envíos.
 */
class EnviarRecordatoriosVencimiento extends Command
{
    protected $signature = 'firma-digital:enviar-recordatorios
                            {--dry-run : Muestra qué se enviaría sin enviar ni modificar registros}';

    protected $description = 'Envía los recordatorios de vencimiento de certificados de firma digital';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $hoy = now()->toDateString();
        $maxIntentos = (int) config('firma_digital.recordatorio_max_intentos', 3);

        $candidatos = CertificadoFirma::with(['entrega.persona', 'entrega.dispositivo'])
            ->actuales()
            ->whereDate('fecha_recordatorio', '<=', $hoy)
            ->where(function ($q) use ($maxIntentos) {
                $q->where('recordatorio_estado', CertificadoFirma::RECORDATORIO_PENDIENTE)
                    ->orWhere(function ($q) use ($maxIntentos) {
                        $q->where('recordatorio_estado', CertificadoFirma::RECORDATORIO_ERROR)
                            ->where('recordatorio_intentos', '<', $maxIntentos);
                    });
            })
            ->whereHas('entrega', fn ($q) => $q->where('estado', EntregaToken::ESTADO_ASIGNADA))
            ->orderBy('fecha_recordatorio')
            ->get();

        $resumen = ['enviados' => 0, 'errores' => 0, 'sin_email' => 0, 'cancelados' => 0];

        $this->info(($dryRun ? '[SIMULACIÓN] ' : '')."Certificados a procesar: {$candidatos->count()}");

        foreach ($candidatos as $certificado) {
            $persona = $certificado->entrega->persona;
            $etiqueta = "#{$certificado->id} ".($persona ? "{$persona->apellido}, {$persona->nombre}" : 'sin persona');

            // Vencido sin haberse enviado el recordatorio: ya no corresponde el aviso previo
            if ($certificado->fecha_vencimiento->toDateString() < $hoy) {
                $this->line("  {$etiqueta}: vencido, se cancela el recordatorio");
                if (! $dryRun) {
                    $this->actualizar($certificado, CertificadoFirma::RECORDATORIO_CANCELADO, 'El certificado venció antes del envío');
                }
                $resumen['cancelados']++;

                continue;
            }

            $email = $this->emailDestino($persona);

            if (! $email) {
                $this->warn("  {$etiqueta}: sin email válido");
                if (! $dryRun) {
                    $this->actualizar($certificado, CertificadoFirma::RECORDATORIO_SIN_EMAIL, 'La persona no tiene un email válido');
                }
                $resumen['sin_email']++;

                continue;
            }

            if ($dryRun) {
                $this->line("  {$etiqueta}: se enviaría a {$email}");
                $resumen['enviados']++;

                continue;
            }

            // Reclamo atómico: si otra ejecución ya lo tomó, no se envía
            $reclamado = CertificadoFirma::whereKey($certificado->id)
                ->whereIn('recordatorio_estado', [CertificadoFirma::RECORDATORIO_PENDIENTE, CertificadoFirma::RECORDATORIO_ERROR])
                ->update(['recordatorio_estado' => CertificadoFirma::RECORDATORIO_ENVIANDO, 'updated_at' => now()]);

            if ($reclamado === 0) {
                continue;
            }

            // Reflejar el reclamo en memoria: si no, volver a ERROR desde ERROR no se detecta como cambio
            // y el registro quedaría trabado en ENVIANDO
            $certificado->forceFill(['recordatorio_estado' => CertificadoFirma::RECORDATORIO_ENVIANDO])
                ->syncOriginalAttribute('recordatorio_estado');

            try {
                Mail::to($email)->send(new RecordatorioVencimientoCertificado($certificado));

                $certificado->forceFill([
                    'recordatorio_estado' => CertificadoFirma::RECORDATORIO_ENVIADO,
                    'recordatorio_enviado_at' => now(),
                    'recordatorio_email' => $email,
                    'recordatorio_intentos' => $certificado->recordatorio_intentos + 1,
                    'recordatorio_ultimo_error' => null,
                ])->save();

                AuditService::log('RECORDATORIO_ENVIADO', 'FIRMA_DIGITAL', $certificado->entrega_token_id, null, [
                    'certificado_id' => $certificado->id,
                    'email' => $email,
                ], 'Recordatorio de vencimiento enviado');

                $this->line("  {$etiqueta}: enviado a {$email}");
                $resumen['enviados']++;
            } catch (Throwable $e) {
                $certificado->forceFill([
                    'recordatorio_estado' => CertificadoFirma::RECORDATORIO_ERROR,
                    'recordatorio_email' => $email,
                    'recordatorio_intentos' => $certificado->recordatorio_intentos + 1,
                    'recordatorio_ultimo_error' => mb_substr($e->getMessage(), 0, 1000),
                ])->save();

                AuditService::log('RECORDATORIO_ERROR', 'FIRMA_DIGITAL', $certificado->entrega_token_id, null, [
                    'certificado_id' => $certificado->id,
                ], 'Error al enviar recordatorio: '.mb_substr($e->getMessage(), 0, 300));

                $this->error("  {$etiqueta}: error al enviar ({$e->getMessage()})");
                $resumen['errores']++;
            }
        }

        $this->info(sprintf(
            'Enviados: %d · Errores: %d · Sin email: %d · Cancelados: %d',
            $resumen['enviados'], $resumen['errores'], $resumen['sin_email'], $resumen['cancelados']
        ));

        return self::SUCCESS;
    }

    /**
     * Email principal; si no es válido, el alternativo
     */
    private function emailDestino($persona): ?string
    {
        foreach ([$persona?->email_principal, $persona?->email_alternativo] as $email) {
            $email = trim((string) $email);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return null;
    }

    private function actualizar(CertificadoFirma $certificado, string $estado, string $detalle): void
    {
        $certificado->forceFill([
            'recordatorio_estado' => $estado,
            'recordatorio_ultimo_error' => $detalle,
        ])->save();

        AuditService::log('RECORDATORIO_'.$estado, 'FIRMA_DIGITAL', $certificado->entrega_token_id, null, [
            'certificado_id' => $certificado->id,
        ], $detalle);
    }
}
