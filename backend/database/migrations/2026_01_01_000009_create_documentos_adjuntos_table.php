<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->string('documentable_type', 100);
            $table->unsignedBigInteger('documentable_id');
            $table->string('tipo_documento', 50); // ACTA_ACUERDO, ANEXO_TECNICO, RESOLUCION_DESIGNACION, CONVENIO
            $table->string('titulo', 255);
            $table->string('archivo_nombre_original', 255);
            $table->string('archivo_path', 500);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('tamanio_bytes');
            $table->string('sha256', 64)->nullable();
            $table->text('observaciones')->nullable();
            $table->unsignedBigInteger('subido_por_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['documentable_type', 'documentable_id']);
            $table->index('tipo_documento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_adjuntos');
    }
};
