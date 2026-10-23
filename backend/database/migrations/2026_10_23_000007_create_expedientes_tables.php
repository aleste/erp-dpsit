<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expediente (identidad) → intervenciones (cada pase por el área) → movimientos (historial, solo inserción)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expedientes', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 100)->unique(); // Normalizado: mayúsculas, espacios unificados
            $table->string('numero_busqueda', 100)->index(); // Solo letras y dígitos, para buscar sin guiones/espacios
            $table->string('formato', 10)->default('GDE'); // GDE, OTRO
            $table->unsignedSmallInteger('anio')->nullable();
            $table->string('reparticion', 50)->nullable(); // Sufijo GDE de la repartición caratuladora
            $table->foreignId('ministerio_id')->nullable()->constrained('ministerios')->nullOnDelete(); // Organismo de origen
            $table->text('caratula')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('ministerio_id');
        });

        Schema::create('expediente_intervenciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes')->restrictOnDelete();
            $table->unsignedSmallInteger('numero_intervencion'); // 1ra, 2da, 3ra... intervención
            $table->date('fecha_ingreso');
            $table->date('fecha_egreso')->nullable(); // Puede faltar en datos importados (salida sin fecha)
            $table->boolean('en_area')->default(true); // false desde el egreso; lo mantiene ExpedienteService
            $table->foreignId('estado_id')->constrained('expediente_estados')->restrictOnDelete();
            $table->foreignId('tipo_requerimiento_id')->nullable()->constrained('expediente_tipos_requerimiento')->nullOnDelete();
            $table->text('descripcion')->nullable(); // Motivo de esta intervención
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha_envio_revision')->nullable();
            $table->date('fecha_firma')->nullable();
            $table->foreignId('dependencia_destino_id')->nullable()->constrained('dependencias_destino')->nullOnDelete();
            $table->string('documento_respuesta', 150)->nullable(); // Número de GEDO (IF-, PV-, NO-...)
            $table->string('prioridad', 10)->default('NORMAL'); // NORMAL, ALTA
            $table->string('proxima_accion', 255)->nullable();
            $table->date('fecha_proxima_accion')->nullable();
            $table->date('fecha_vencimiento')->nullable(); // Solo si existe un plazo real
            $table->string('etb', 100)->nullable(); // Columna de la planilla, significado a confirmar (P8)
            $table->string('con_acto_administrativo', 20)->nullable(); // SI, NO_CORRESPONDE, PENDIENTE (P9)
            $table->text('observaciones')->nullable();
            $table->string('origen', 20)->default('SISTEMA'); // SISTEMA, PLANILLA
            $table->string('origen_referencia', 100)->nullable(); // Hoja y fila de la planilla importada
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['expediente_id', 'numero_intervencion']);
            $table->index(['estado_id', 'en_area']);
            $table->index(['responsable_id', 'en_area']);
            $table->index('fecha_ingreso');
            $table->index('fecha_egreso');
            $table->index('fecha_proxima_accion');
        });

        // A lo sumo una intervención abierta (en el área) por expediente
        DB::statement('CREATE UNIQUE INDEX uq_expediente_intervencion_abierta ON expediente_intervenciones (expediente_id) WHERE en_area AND deleted_at IS NULL');

        Schema::create('expediente_intervencion_colaboradores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intervencion_id')->constrained('expediente_intervenciones')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['intervencion_id', 'user_id']);
        });

        Schema::create('expediente_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intervencion_id')->constrained('expediente_intervenciones')->restrictOnDelete();
            $table->date('fecha');
            $table->string('tipo', 30); // INGRESO, ASIGNACION, CAMBIO_ESTADO, ENVIO_REVISION, FIRMA, EGRESO, REAPERTURA, NOTA, ACTUALIZACION, CORRECCION
            $table->foreignId('estado_anterior_id')->nullable()->constrained('expediente_estados')->nullOnDelete();
            $table->foreignId('estado_nuevo_id')->nullable()->constrained('expediente_estados')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // Autor (NULL en datos importados)
            $table->text('descripcion')->nullable();
            $table->string('referencia_documento', 150)->nullable();
            $table->date('fecha_proximo_paso')->nullable();
            $table->boolean('importado')->default(false);
            $table->timestamp('created_at')->useCurrent(); // Solo inserción: sin updated_at

            $table->index(['intervencion_id', 'fecha']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expediente_movimientos');
        Schema::dropIfExists('expediente_intervencion_colaboradores');
        Schema::dropIfExists('expediente_intervenciones');
        Schema::dropIfExists('expedientes');
    }
};
