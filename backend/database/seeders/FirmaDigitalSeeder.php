<?php

namespace Database\Seeders;

use App\Models\DispositivoFirma;
use App\Models\EntregaToken;
use App\Models\Ministerio;
use App\Models\Persona;
use App\Services\FirmaDigitalService;
use Illuminate\Database\Seeder;

/**
 * Datos demo FICTICIOS del módulo de firma digital. Las fechas son relativas al día de ejecución
 * para que siempre haya certificados vigentes, próximos a vencer y vencidos.
 */
class FirmaDigitalSeeder extends Seeder
{
    public function run(): void
    {
        if (EntregaToken::exists()) {
            return; // Idempotente: no duplica datos demo
        }

        $servicio = app(FirmaDigitalService::class);
        $hoy = now()->startOfDay();
        $ministerio = Ministerio::orderBy('orden_prioridad')->first();
        $personas = Persona::orderBy('id')->take(3)->get();

        // Persona externa al ERP (sin CUIL, sin organismo del ERP y sin email)
        $externa = Persona::firstOrCreate(
            ['tipo_documento' => 'DNI', 'numero_documento' => '99000111'],
            ['apellido' => 'Ejemplo', 'nombre' => 'Médico Externo', 'observaciones' => 'Persona ficticia de demostración (Autoridad de Registro).']
        );

        $casos = [
            // [persona, organismo, fecha de entrega (días atrás), serie, descripción]
            [$personas[0] ?? $externa, ['ministerio_id' => $ministerio?->id], 60, 'DEMO-0001', 'vigente'],
            [$personas[1] ?? $externa, ['ministerio_id' => $ministerio?->id], 340, 'DEMO-0002', 'recordatorio vencido de envío (se envía en la próxima ejecución)'],
            [$personas[2] ?? $externa, ['ministerio_id' => $ministerio?->id], 380, 'DEMO-0003', 'vencido'],
            [$externa, ['organismo_externo' => 'Hospital Privado de Ejemplo'], 340, 'DEMO-0004', 'externo sin email'],
        ];

        foreach ($casos as [$persona, $organismo, $diasAtras, $serie, $descripcion]) {
            $dispositivo = DispositivoFirma::create([
                'tipo_dispositivo' => 'TOKEN_USB',
                'marca' => 'MarcaDemo',
                'modelo' => 'Token Demo 1',
                'numero_serie' => $serie,
                'estado' => 'ASIGNADO',
                'observaciones' => "Dispositivo ficticio ({$descripcion}).",
            ]);

            $fechaEntrega = $hoy->copy()->subDays($diasAtras)->toDateString();

            $entrega = EntregaToken::create(array_merge([
                'persona_id' => $persona->id,
                'dispositivo_firma_id' => $dispositivo->id,
                'cargo_funcion' => $persona->is($externa) ? 'Autoridad de Registro' : null,
                'fecha_entrega' => $fechaEntrega,
                'estado' => EntregaToken::ESTADO_ASIGNADA,
            ], $organismo));

            $entrega->certificados()->create($servicio->resolverFechas(['fecha_inicio' => $fechaEntrega]));
        }

        // Una entrega histórica ya devuelta, para mostrar el historial
        $devuelto = DispositivoFirma::create([
            'tipo_dispositivo' => 'TOKEN_USB',
            'marca' => 'MarcaDemo',
            'modelo' => 'Token Demo 0',
            'numero_serie' => 'DEMO-0000',
            'estado' => 'DEVUELTO',
        ]);

        $historica = EntregaToken::create([
            'persona_id' => ($personas[0] ?? $externa)->id,
            'dispositivo_firma_id' => $devuelto->id,
            'ministerio_id' => $ministerio?->id,
            'fecha_entrega' => $hoy->copy()->subYears(2)->toDateString(),
            'fecha_fin' => $hoy->copy()->subDays(60)->toDateString(),
            'estado' => 'REEMPLAZADA',
            'motivo_fin' => 'Reemplazada por un dispositivo nuevo (dato ficticio).',
        ]);

        $historica->certificados()->create(array_merge(
            $servicio->resolverFechas(['fecha_inicio' => $historica->fecha_entrega]),
            ['recordatorio_estado' => 'CANCELADO']
        ));
    }
}
