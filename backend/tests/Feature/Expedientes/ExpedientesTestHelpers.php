<?php

namespace Tests\Feature\Expedientes;

use App\Models\ExpedienteEstado;
use App\Models\User;
use Database\Seeders\ExpedientesCatalogoSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\UsuariosDePrueba;

trait ExpedientesTestHelpers
{
    use UsuariosDePrueba;

    protected function sembrarCatalogos(): void
    {
        $this->seed(ExpedientesCatalogoSeeder::class);
    }

    /**
     * Usuario con perfil global y, opcionalmente, un rol en el módulo de expedientes
     */
    protected function usuario(string $perfil = 'LECTOR', ?string $rolExpedientes = null, string $nombre = ''): User
    {
        return $this->crearUsuario(
            $perfil,
            $rolExpedientes ? ['EXPEDIENTES' => $rolExpedientes] : [],
            $nombre ?: "Usuario {$perfil} {$rolExpedientes}",
        );
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
