<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicio_referentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servicio_area_id')->constrained('servicios_areas')->cascadeOnDelete();
            $table->foreignId('persona_id')->constrained('personas')->restrictOnDelete();
            $table->string('tipo_referente', 30)->default('TECNICO_PRINCIPAL'); // TECNICO_PRINCIPAL, TECNICO_ALTERNO, ADMINISTRATIVO, SEGURIDAD
            $table->boolean('es_principal')->default(false);
            $table->boolean('notificaciones_activas')->default(true);
            $table->timestamps();

            $table->unique(['servicio_area_id', 'persona_id', 'tipo_referente'], 'uq_srv_per_tipo');
            $table->index(['servicio_area_id', 'tipo_referente']);
            $table->index(['persona_id', 'tipo_referente']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicio_referentes');
    }
};
