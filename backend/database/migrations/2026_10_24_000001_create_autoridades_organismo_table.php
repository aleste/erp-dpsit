<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Máxima autoridad de cada ministerio u organismo (D-32), con historial: una designación nueva
 * cierra la vigente. El cargo es texto libre porque varía según el organismo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autoridades_organismo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ministerio_id')->constrained('ministerios')->restrictOnDelete();
            $table->foreignId('persona_id')->constrained('personas')->restrictOnDelete();
            $table->string('cargo', 150)->nullable(); // Ministro/a, Presidente, Fiscal de Estado...
            $table->date('fecha_inicio')->nullable(); // NULL = fecha de asunción desconocida
            $table->date('fecha_fin')->nullable(); // NULL = vigente
            $table->string('acto_administrativo', 150)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ministerio_id', 'fecha_fin']);
            $table->index('persona_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autoridades_organismo');
    }
};
