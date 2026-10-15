<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versiones publicadas de la app de escritorio «Puente de datos» (instalador o portable).
 * Aditiva: no toca ninguna tabla existente. Las versiones se retiran, no se borran.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('puente_versiones')) {
            return;
        }
        Schema::create('puente_versiones', function (Blueprint $table) {
            $table->id();
            $table->string('version', 20);
            $table->string('tipo', 20);
            $table->text('notas')->nullable();
            $table->string('nombre_original', 255);
            $table->string('ruta', 500);
            $table->unsignedBigInteger('tamano');
            $table->char('sha256', 64);
            $table->unsignedInteger('descargas')->default(0);
            $table->foreignId('subido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('retirada_en')->nullable();
            $table->foreignId('retirada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_retiro', 500)->nullable();
            $table->timestamps();
            $table->unique(['version', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('puente_versiones');
    }
};
