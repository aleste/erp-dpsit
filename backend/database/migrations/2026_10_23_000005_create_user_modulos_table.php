<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permisos por módulo (D-26): rol de un usuario dentro de un módulo, además de su perfil global.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_modulos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('modulo', 50); // EXPEDIENTES, ...
            $table->string('rol', 30); // EXPEDIENTES: CONSULTA, OPERADOR, SUPERVISOR
            $table->timestamps();

            $table->unique(['user_id', 'modulo']);
            $table->index(['modulo', 'rol']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_modulos');
    }
};
