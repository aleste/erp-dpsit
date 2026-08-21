<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Mapeo polimórfico limpio para Documentos Adjuntos y Sanctum Tokens
        Relation::morphMap([
            'user' => \App\Models\User::class,
            'area_ti' => \App\Models\AreaTi::class,
            'servicio_area' => \App\Models\ServicioArea::class,
            'asignacion_rol' => \App\Models\AsignacionRol::class,
            'persona' => \App\Models\Persona::class,
        ]);
    }
}
