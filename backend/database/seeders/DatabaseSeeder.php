<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            MinisterioSeeder::class,
            AreaTiSeeder::class,
            TipoRolSeeder::class,
            PersonaSeeder::class,
            AsignacionRolSeeder::class,
            CatalogoServicioSeeder::class,
            ServicioAreaSeeder::class,
            UserSeeder::class,
            FirmaDigitalSeeder::class,
        ]);
    }
}
