<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicios_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_ti_id')->constrained('areas_ti')->cascadeOnDelete();
            $table->foreignId('catalogo_servicio_id')->constrained('catalogo_servicios')->restrictOnDelete();
            $table->string('codigo_instancia', 100)->unique();
            $table->string('estado_servicio', 30)->default('ACTIVO'); // SOLICITADO, EN_PROCESO, ACTIVO, SUSPENDIDO, DADO_DE_BAJA
            $table->string('sla_nivel', 30)->default('ESTANDAR');
            $table->date('fecha_alta');
            $table->date('fecha_baja')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['area_ti_id', 'estado_servicio']);
            $table->index(['catalogo_servicio_id', 'estado_servicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicios_areas');
    }
};
