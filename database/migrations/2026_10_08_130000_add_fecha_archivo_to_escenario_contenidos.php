<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha del archivo según el navegador (File.lastModified) al subirlo. Decide si un informe
 * que ya existe se actualiza (archivo más reciente) o no se carga (igual o más antiguo).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('escenario_contenidos', 'fecha_archivo')) {
            Schema::table('escenario_contenidos', function (Blueprint $table) {
                $table->dateTime('fecha_archivo')->nullable()->after('tamano');
            });
        }
    }

    public function down(): void
    {
        Schema::table('escenario_contenidos', function (Blueprint $table) {
            $table->dropColumn('fecha_archivo');
        });
    }
};
