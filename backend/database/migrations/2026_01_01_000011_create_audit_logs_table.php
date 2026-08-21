<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('accion', 50); // LOGIN, CREATE, UPDATE, DELETE, EXPORT_EMAILS
            $table->string('modulo', 50); // PERSONAS, ROLES, SERVICIOS, EXPORT
            $table->unsignedBigInteger('registro_id')->nullable();
            $table->jsonb('valores_anteriores')->nullable();
            $table->jsonb('valores_nuevos')->nullable();
            $table->text('detalles')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['modulo', 'accion']);
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
