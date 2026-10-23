<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo de firma digital (D-19): los titulares de tokens se registran en el padrón de personas,
 * por lo que CUIL, email y teléfono dejan de ser obligatorios y se agrega el documento de identidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->string('cuil_cuit', 13)->nullable()->change();
            $table->string('email_principal', 255)->nullable()->change();
            $table->string('telefono_principal', 50)->nullable()->change();

            $table->string('tipo_documento', 20)->nullable()->after('cuil_cuit'); // DNI, PASAPORTE, CI, LC, LE, OTRO
            $table->string('numero_documento', 30)->nullable()->after('tipo_documento');

            $table->unique(['tipo_documento', 'numero_documento'], 'uq_personas_documento');
        });
    }

    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->dropUnique('uq_personas_documento');
            $table->dropColumn(['tipo_documento', 'numero_documento']);

            // Requiere que no existan personas sin CUIL, email o teléfono
            $table->string('cuil_cuit', 13)->nullable(false)->change();
            $table->string('email_principal', 255)->nullable(false)->change();
            $table->string('telefono_principal', 50)->nullable(false)->change();
        });
    }
};
