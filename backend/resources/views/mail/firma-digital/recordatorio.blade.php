<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Vencimiento de certificado de firma digital</title>
</head>
<body style="margin:0; padding:24px; background:#f1f5f9; font-family:Arial, Helvetica, sans-serif; color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; margin:0 auto; background:#ffffff; border-radius:8px; overflow:hidden;">
        <tr>
            <td style="background:#0f172a; color:#ffffff; padding:20px 24px;">
                <div style="font-size:12px; letter-spacing:1px; text-transform:uppercase; color:#7dd3fc;">{{ $agencia['short_name'] ?? '' }}</div>
                <div style="font-size:16px; font-weight:bold; margin-top:4px;">{{ $agencia['name'] ?? '' }}</div>
            </td>
        </tr>
        <tr>
            <td style="padding:24px; font-size:14px; line-height:1.6;">
                <p>Estimado/a {{ $persona->nombre }} {{ $persona->apellido }}:</p>

                <p>
                    Le informamos que el certificado de firma digital contenido en el dispositivo que le fue entregado
                    <strong>vence el {{ $fechaVencimiento }}</strong>
                    @if ($diasParaVencer > 0)
                        (en {{ $diasParaVencer }} {{ $diasParaVencer === 1 ? 'día' : 'días' }}).
                    @else
                        .
                    @endif
                </p>

                @if ($dispositivo)
                    <p style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:12px;">
                        Dispositivo: {{ trim(($dispositivo->marca ?? '') . ' ' . ($dispositivo->modelo ?? '')) ?: $dispositivo->tipo_dispositivo }}
                        @if ($dispositivo->numero_serie)
                            <br>N.º de serie: {{ $dispositivo->numero_serie }}
                        @endif
                    </p>
                @endif

                <p>Para evitar interrupciones en la firma de documentos, le recomendamos iniciar el trámite de renovación con anticipación.</p>

                <p style="color:#64748b; font-size:12px; margin-top:24px;">
                    Este es un mensaje automático de cortesía de {{ $agencia['name'] ?? 'la Dirección' }}. Por favor, no responda a este correo.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
