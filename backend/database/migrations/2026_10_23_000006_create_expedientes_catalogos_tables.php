<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogos configurables del módulo de expedientes (D-30)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expediente_estados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('color', 20)->default('slate'); // Paleta Tailwind para la UI
            $table->boolean('es_final')->default(false); // Estado de egreso (la intervención sale del área)
            $table->boolean('requiere_documento')->default(true); // Estados finales: exige documento de respuesta al egresar
            $table->boolean('exime_documento')->default(false); // Egresar DESDE este estado no exige documento (p. ej. pase simple)
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('expediente_tipos_requerimiento', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150)->unique();
            $table->string('grupo', 50)->nullable(); // PERSONAL, BIENES_SERVICIOS, NORMATIVA, OTROS
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('dependencias_destino', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 255)->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dependencias_destino');
        Schema::dropIfExists('expediente_tipos_requerimiento');
        Schema::dropIfExists('expediente_estados');
    }
};
