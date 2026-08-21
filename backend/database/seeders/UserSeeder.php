<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Administrador DPSIT',
                'email' => 'admin@dpsit.gob.ar',
                'password' => Hash::make('admin123'),
                'perfil_acceso' => 'ADMINISTRADOR',
                'activo' => true,
            ],
            [
                'name' => 'Operador Editor TI',
                'email' => 'editor@dpsit.gob.ar',
                'password' => Hash::make('editor123'),
                'perfil_acceso' => 'EDITOR',
                'activo' => true,
            ],
            [
                'name' => 'Auditor / Lector',
                'email' => 'lector@dpsit.gob.ar',
                'password' => Hash::make('lector123'),
                'perfil_acceso' => 'LECTOR',
                'activo' => true,
            ],
        ];

        foreach ($users as $u) {
            User::updateOrCreate(['email' => $u['email']], $u);
        }
    }
}
