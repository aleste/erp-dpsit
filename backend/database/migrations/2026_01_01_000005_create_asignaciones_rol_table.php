<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignaciones_rol', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $table->foreignId('area_ti_id')->constrained('areas_ti')->cascadeOnDelete();
            $table->foreignId('tipo_rol_id')->constrained('tipos_rol')->restrictOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable(); // NULL = Vigente actual
            $table->boolean('es_maxima_autoridad')->default(false);
            $table->string('acto_administrativo', 150)->nullable();
            $table->text('observaciones')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['persona_id', 'activo']);
            $table->index(['area_ti_id', 'activo']);
            $table->index(['tipo_rol_id', 'activo']);
            $table->index(['fecha_inicio', 'fecha_fin']);
            $table->index(['area_ti_id', 'es_maxima_autoridad', 'fecha_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones_rol');
    }
};
