<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entregas_token', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas')->restrictOnDelete();
            $table->foreignId('dispositivo_firma_id')->constrained('dispositivos_firma')->restrictOnDelete();
            // Organismo al momento de la entrega (D-20): ministerio del ERP, organismo externo o ninguno
            $table->foreignId('ministerio_id')->nullable()->constrained('ministerios')->nullOnDelete();
            $table->string('organismo_externo', 255)->nullable();
            $table->string('cargo_funcion', 150)->nullable();
            $table->date('fecha_entrega');
            $table->date('fecha_fin')->nullable();
            $table->string('estado', 30)->default('ASIGNADA'); // ASIGNADA, DEVUELTA, REEMPLAZADA, EXTRAVIADA, REVOCADA
            $table->text('motivo_fin')->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('registrado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['persona_id', 'estado']);
            $table->index(['ministerio_id', 'estado']);
            $table->index('fecha_entrega');
        });

        // Un dispositivo puede tener como máximo una entrega ASIGNADA vigente
        DB::statement("CREATE UNIQUE INDEX uq_entregas_token_dispositivo_asignado ON entregas_token (dispositivo_firma_id) WHERE estado = 'ASIGNADA' AND deleted_at IS NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('entregas_token');
    }
};
