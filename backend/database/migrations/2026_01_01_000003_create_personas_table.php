<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personas', function (Blueprint $table) {
            $table->id();
            $table->string('cuil_cuit', 13)->unique();
            $table->string('apellido', 100);
            $table->string('nombre', 100);
            $table->string('email_principal', 255)->unique();
            $table->string('email_alternativo', 255)->nullable();
            $table->string('telefono_principal', 50);
            $table->string('telefono_alternativo', 50)->nullable();
            $table->string('titulo_profesional', 150)->nullable();
            $table->text('observaciones')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['apellido', 'nombre']);
            $table->index('email_principal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
