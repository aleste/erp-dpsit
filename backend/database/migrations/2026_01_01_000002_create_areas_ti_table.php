<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas_ti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ministerio_id')->constrained('ministerios')->restrictOnDelete();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 255);
            $table->string('nivel_jerarquico', 50)->default('DIRECCION_PROVINCIAL');
            $table->boolean('es_homologada')->default(true);
            $table->string('resolucion_homologacion', 100)->nullable();
            $table->string('domicilio_fisico', 255)->nullable();
            $table->string('telefono_contacto', 50)->nullable();
            $table->string('email_institucional', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ministerio_id', 'activo']);
            $table->index(['es_homologada', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('areas_ti');
    }
};
