<?php

namespace Tests\Feature\FirmaDigital;

use App\Models\CertificadoFirma;
use App\Models\DispositivoFirma;
use App\Models\EntregaToken;
use App\Models\Ministerio;
use App\Models\Persona;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

trait FirmaDigitalTestHelpers
{
    protected function actuarComo(string $perfil): User
    {
        $user = User::create([
            'name' => "Usuario {$perfil}",
            'email' => strtolower($perfil).'@test.local',
            'password' => 'secreto-de-prueba',
            'perfil_acceso' => $perfil,
            'activo' => true,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    protected function crearPersona(array $atributos = []): Persona
    {
        return Persona::create(array_merge([
            'apellido' => 'Prueba',
            'nombre' => 'Persona',
            'email_principal' => 'persona'.uniqid().'@test.local',
        ], $atributos));
    }

    protected function crearMinisterio(): Ministerio
    {
        return Ministerio::create(['codigo' => 'MT'.random_int(100, 999), 'nombre' => 'Ministerio de Pruebas']);
    }

    /**
     * Entrega ASIGNADA con un certificado de las fechas indicadas
     */
    protected function crearEntregaConCertificado(Persona $persona, string $vencimiento, string $recordatorio, array $certificado = []): CertificadoFirma
    {
        $dispositivo = DispositivoFirma::create(['tipo_dispositivo' => 'TOKEN_USB', 'estado' => 'ASIGNADO']);

        $entrega = EntregaToken::create([
            'persona_id' => $persona->id,
            'dispositivo_firma_id' => $dispositivo->id,
            'fecha_entrega' => '2025-11-01',
            'estado' => EntregaToken::ESTADO_ASIGNADA,
        ]);

        return $entrega->certificados()->create(array_merge([
            'fecha_inicio' => '2025-11-01',
            'fecha_vencimiento' => $vencimiento,
            'fecha_recordatorio' => $recordatorio,
            'recordatorio_estado' => CertificadoFirma::RECORDATORIO_PENDIENTE,
        ], $certificado));
    }
}
