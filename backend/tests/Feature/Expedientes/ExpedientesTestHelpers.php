<?php

namespace Tests\Feature\Expedientes;

use App\Models\ExpedienteEstado;
use App\Models\User;
use Database\Seeders\ExpedientesCatalogoSeeder;
use Laravel\Sanctum\Sanctum;

trait ExpedientesTestHelpers
{
    protected function sembrarCatalogos(): void
    {
        $this->seed(ExpedientesCatalogoSeeder::class);
    }

    /**
     * Usuario con perfil global y, opcionalmente, un rol en el módulo de expedientes
     */
    protected function usuario(string $perfil = 'LECTOR', ?string $rolExpedientes = null, string $nombre = ''): User
    {
        $user = User::create([
            'name' => $nombre ?: "Usuario {$perfil} {$rolExpedientes}",
            'email' => uniqid('u').'@test.local',
            'password' => 'secreto-de-prueba',
            'perfil_acceso' => $perfil,
            'activo' => true,
        ]);

        if ($rolExpedientes) {
            $user->modulos()->create(['modulo' => 'EXPEDIENTES', 'rol' => $rolExpedientes]);
        }

        return $user->load('modulos');
    }

    protected function actuarComo(User $user): User
    {
        Sanctum::actingAs($user);

        return $user;
    }

    protected function estado(string $nombre): ExpedienteEstado
    {
        return ExpedienteEstado::where('nombre', $nombre)->firstOrFail();
    }

    protected function ingresar(array $datos = []): array
    {
        return $this->postJson('/api/expedientes', array_merge([
            'numero' => 'EX-2026-12345678- -GDEBA-DPTEST',
            'fecha_ingreso' => '2026-10-01',
            'caratula' => 'Solicitud de prueba',
        ], $datos))->assertCreated()->json('data');
    }
}
