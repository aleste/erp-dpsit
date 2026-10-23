<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificados_firma', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrega_token_id')->constrained('entregas_token')->cascadeOnDelete();
            $table->date('fecha_inicio'); // Por defecto = fecha de entrega; puede diferir
            $table->date('fecha_vencimiento');
            $table->boolean('vencimiento_manual')->default(false);
            $table->date('fecha_recordatorio');
            $table->boolean('recordatorio_manual')->default(false);
            $table->string('recordatorio_estado', 20)->default('PENDIENTE'); // PENDIENTE, ENVIANDO, ENVIADO, ERROR, SIN_EMAIL, CANCELADO
            $table->timestamp('recordatorio_enviado_at')->nullable();
            $table->string('recordatorio_email', 255)->nullable();
            $table->unsignedSmallInteger('recordatorio_intentos')->default(0);
            $table->text('recordatorio_ultimo_error')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['recordatorio_estado', 'fecha_recordatorio']);
            $table->index('fecha_vencimiento');
            $table->index(['entrega_token_id', 'fecha_inicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificados_firma');
    }
};
