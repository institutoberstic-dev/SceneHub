<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo territorial (aditivo): paquetes versionados del PT1, sus archivos,
 * las validaciones ligeras y el registro de quién hizo qué y cuándo.
 * Ninguna tabla existente se modifica. Las FK hacia paquetes usan RESTRICT:
 * un paquete con archivos no se puede borrar (se retira).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('paquete_territorial')) {
            Schema::create('paquete_territorial', function (Blueprint $table) {
                $table->id();
                $table->string('version', 20)->unique();
                $table->string('estado', 20)->default('borrador'); // borrador | validado | publicado | retirado
                $table->text('notas')->nullable();
                $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('publicado_en')->nullable();
                $table->foreignId('publicado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('retirado_en')->nullable();
                $table->foreignId('retirado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->text('motivo_retiro')->nullable();
                $table->timestamps();
                $table->index('estado');
            });
        }

        if (! Schema::hasTable('archivo_territorial')) {
            Schema::create('archivo_territorial', function (Blueprint $table) {
                $table->id();
                $table->foreignId('paquete_id')->constrained('paquete_territorial')->restrictOnDelete();
                $table->string('tipo', 30);
                $table->string('descripcion', 255)->nullable();
                $table->string('capa', 120)->nullable();
                $table->string('nombre_original', 255);
                $table->string('nombre', 255);
                $table->string('ruta', 500);
                $table->unsignedBigInteger('tamano');
                $table->string('mime_type', 120)->nullable();
                $table->char('sha256', 64);
                $table->char('sha256_declarado', 64)->nullable();
                $table->string('crs', 20)->nullable();
                $table->string('fuente', 255)->nullable();
                $table->string('licencia', 255)->nullable();
                $table->date('fecha_captura')->nullable();
                $table->boolean('es_sensible')->default(false);
                $table->foreignId('subido_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['paquete_id', 'tipo']);
            });
        }

        if (! Schema::hasTable('validacion_territorial')) {
            Schema::create('validacion_territorial', function (Blueprint $table) {
                $table->id();
                $table->foreignId('paquete_id')->constrained('paquete_territorial')->restrictOnDelete();
                $table->foreignId('archivo_id')->nullable()->constrained('archivo_territorial')->nullOnDelete();
                $table->string('regla', 60);
                $table->string('resultado', 12); // ok | advertencia | error
                $table->text('detalle')->nullable();
                $table->boolean('requiere_confirmacion')->default(false);
                $table->dateTime('confirmada_en')->nullable();
                $table->foreignId('confirmada_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['paquete_id', 'resultado']);
            });
        }

        if (! Schema::hasTable('registro_territorial')) {
            Schema::create('registro_territorial', function (Blueprint $table) {
                $table->id();
                $table->foreignId('paquete_id')->constrained('paquete_territorial')->restrictOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('accion', 40);
                $table->text('detalle')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('registro_territorial');
        Schema::dropIfExists('validacion_territorial');
        Schema::dropIfExists('archivo_territorial');
        Schema::dropIfExists('paquete_territorial');
    }
};
