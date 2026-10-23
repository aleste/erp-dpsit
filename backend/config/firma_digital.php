<?php

/*
|--------------------------------------------------------------------------
| Módulo de Dispositivos de Firma Digital (Tokens)
|--------------------------------------------------------------------------
| Parámetros de vigencia y recordatorios. Cambiarlos afecta a los registros
| nuevos o editados; los existentes conservan sus fechas almacenadas.
*/

return [
    // Vigencia predeterminada del certificado digital, en meses desde su fecha de inicio
    'vigencia_meses' => (int) env('FIRMA_VIGENCIA_MESES', 12),

    // Anticipación predeterminada del recordatorio, en días antes del vencimiento
    'recordatorio_dias_anticipacion' => (int) env('FIRMA_RECORDATORIO_DIAS', 30),

    // Hora diaria de ejecución del envío automático (formato HH:MM)
    'recordatorio_hora' => env('FIRMA_RECORDATORIO_HORA', '08:00'),

    // Cantidad máxima de intentos ante errores de envío
    'recordatorio_max_intentos' => (int) env('FIRMA_RECORDATORIO_MAX_INTENTOS', 3),
];
