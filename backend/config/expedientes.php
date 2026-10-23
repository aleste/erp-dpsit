<?php

/*
|--------------------------------------------------------------------------
| Módulo de Seguimiento de Expedientes
|--------------------------------------------------------------------------
| La planilla no registraba plazos: no se calcula ningún vencimiento automático (D-31).
| El umbral solo resalta los expedientes con muchos días en el área (en 2026: mediana 6, p90 28).
*/

return [
    // Días en el área a partir de los cuales un expediente se marca como demorado en el tablero
    'umbral_dias_alerta' => (int) env('EXPEDIENTES_UMBRAL_DIAS', 30),
];
