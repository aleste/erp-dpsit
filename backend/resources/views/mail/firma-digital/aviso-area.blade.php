<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Vencimiento de token de firma digital</title>
</head>
<body style="margin:0; padding:24px; background:#f1f5f9; font-family:Arial, Helvetica, sans-serif; color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; margin:0 auto; background:#ffffff; border-radius:8px; overflow:hidden;">
        <tr>
            <td style="background:#0f172a; color:#ffffff; padding:20px 24px;">
                <div style="font-size:12px; letter-spacing:1px; text-transform:uppercase; color:#7dd3fc;">{{ $agencia['short_name'] ?? '' }} · Firma digital</div>
                <div style="font-size:16px; font-weight:bold; margin-top:4px;">Aviso de vencimiento de token</div>
            </td>
        </tr>
        <tr>
            <td style="padding:24px; font-size:14px; line-height:1.6;">
                <p>
                    El certificado de firma digital del token de <strong>{{ $persona->apellido }}, {{ $persona->nombre }}</strong>
                    <strong>vence el {{ $fechaVencimiento }}</strong>
                    @if ($diasParaVencer > 0)
                        (en {{ $diasParaVencer }} {{ $diasParaVencer === 1 ? 'día' : 'días' }}).
                    @else
                        .
                    @endif
                </p>

                @if ($emailTitular)
                    <p style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:6px; padding:12px; color:#065f46;">
                        Se envió el recordatorio al titular ({{ $emailTitular }}).
                    </p>
                @else
                    <p style="background:#fff7ed; border:1px solid #fed7aa; border-radius:6px; padding:12px; color:#9a3412;">
                        <strong>No se pudo notificar al titular:</strong> no tiene un email válido registrado.
                        Es necesario contactarlo por otro medio.
                    </p>
                @endif

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px; border-collapse:collapse;">
                    <tr><td style="padding:6px 0; color:#64748b; width:40%;">Titular</td><td style="padding:6px 0;">{{ $persona->apellido }}, {{ $persona->nombre }}</td></tr>
                    @if ($persona->cuil_cuit)
                        <tr><td style="padding:6px 0; color:#64748b;">CUIL/CUIT</td><td style="padding:6px 0;">{{ $persona->cuil_cuit }}</td></tr>
                    @elseif ($persona->numero_documento)
                        <tr><td style="padding:6px 0; color:#64748b;">Documento</td><td style="padding:6px 0;">{{ $persona->tipo_documento }} {{ $persona->numero_documento }}</td></tr>
                    @endif
                    @if ($persona->telefono_principal)
                        <tr><td style="padding:6px 0; color:#64748b;">Teléfono</td><td style="padding:6px 0;">{{ $persona->telefono_principal }}</td></tr>
                    @endif
                    @if ($entrega->organismo_nombre)
                        <tr><td style="padding:6px 0; color:#64748b;">Organismo</td><td style="padding:6px 0;">{{ $entrega->organismo_nombre }}</td></tr>
                    @endif
                    @if ($entrega->cargo_funcion)
                        <tr><td style="padding:6px 0; color:#64748b;">Cargo o función</td><td style="padding:6px 0;">{{ $entrega->cargo_funcion }}</td></tr>
                    @endif
                    @if ($dispositivo)
                        <tr><td style="padding:6px 0; color:#64748b;">Dispositivo</td><td style="padding:6px 0;">{{ trim(($dispositivo->marca ?? '') . ' ' . ($dispositivo->modelo ?? '')) ?: $dispositivo->tipo_dispositivo }}</td></tr>
                        @if ($dispositivo->numero_serie)
                            <tr><td style="padding:6px 0; color:#64748b;">N.º de serie</td><td style="padding:6px 0;">{{ $dispositivo->numero_serie }}</td></tr>
                        @endif
                    @endif
                    <tr><td style="padding:6px 0; color:#64748b;">Fecha de entrega</td><td style="padding:6px 0;">{{ $entrega->fecha_entrega?->format('d/m/Y') }}</td></tr>
                </table>

                <p style="color:#64748b; font-size:12px; margin-top:24px;">
                    Aviso automático del módulo de firma digital de {{ $agencia['name'] ?? 'la Dirección' }}.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
