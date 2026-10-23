<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recordatorios de vencimiento de certificados de firma digital (ejecutado por el contenedor erp_scheduler)
Schedule::command('firma-digital:enviar-recordatorios')
    ->dailyAt(config('firma_digital.recordatorio_hora', '08:00'))
    ->withoutOverlapping();
