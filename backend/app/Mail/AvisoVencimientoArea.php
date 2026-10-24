<?php

namespace App\Mail;

use App\Models\CertificadoFirma;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso al área de gestión de tokens sobre el próximo vencimiento de un certificado, con los datos
 * para gestionar la renovación. Se envía junto con el recordatorio al titular o, si el titular no
 * tiene email válido, en su lugar (D-25).
 */
class AvisoVencimientoArea extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string|null  $emailTitular  Dirección a la que se notificó al titular; null si no tiene email válido
     */
    public function __construct(
        public CertificadoFirma $certificado,
        public ?string $emailTitular
    ) {}

    public function envelope(): Envelope
    {
        $persona = $this->certificado->entrega->persona;

        return new Envelope(
            subject: "Vence el token de {$persona->apellido}, {$persona->nombre} el ".$this->certificado->fecha_vencimiento->format('d/m/Y')
                .($this->emailTitular ? '' : ' (titular sin email)'),
        );
    }

    public function content(): Content
    {
        $entrega = $this->certificado->entrega;

        return new Content(
            view: 'mail.firma-digital.aviso-area',
            with: [
                'entrega' => $entrega,
                'persona' => $entrega->persona,
                'dispositivo' => $entrega->dispositivo,
                'fechaVencimiento' => $this->certificado->fecha_vencimiento->format('d/m/Y'),
                'diasParaVencer' => $this->certificado->dias_para_vencer,
                'emailTitular' => $this->emailTitular,
                'agencia' => config('app.agency'),
            ],
        );
    }
}
