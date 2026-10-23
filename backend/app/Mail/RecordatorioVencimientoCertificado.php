<?php

namespace App\Mail;

use App\Models\CertificadoFirma;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo de cortesía previo al vencimiento del certificado digital de un token.
 * Se envía de forma sincrónica desde el comando firma-digital:enviar-recordatorios.
 */
class RecordatorioVencimientoCertificado extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public CertificadoFirma $certificado
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Su certificado de firma digital vence el '.$this->certificado->fecha_vencimiento->format('d/m/Y'),
        );
    }

    public function content(): Content
    {
        $entrega = $this->certificado->entrega;

        return new Content(
            view: 'mail.firma-digital.recordatorio',
            with: [
                'persona' => $entrega->persona,
                'dispositivo' => $entrega->dispositivo,
                'fechaVencimiento' => $this->certificado->fecha_vencimiento->format('d/m/Y'),
                'diasParaVencer' => $this->certificado->dias_para_vencer,
                'agencia' => config('app.agency'),
            ],
        );
    }
}
