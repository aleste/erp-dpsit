<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispositivos_firma', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_dispositivo', 30)->default('TOKEN_USB'); // TOKEN_USB, TARJETA_CHIP, OTRO
            $table->string('marca', 100)->nullable();
            $table->string('modelo', 100)->nullable();
            $table->string('numero_serie', 100)->nullable()->unique();
            $table->string('estado', 30)->default('DISPONIBLE'); // DISPONIBLE, ASIGNADO, DEVUELTO, EXTRAVIADO, DADO_DE_BAJA
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispositivos_firma');
    }
};
