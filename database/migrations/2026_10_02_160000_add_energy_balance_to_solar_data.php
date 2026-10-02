<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas del formato extendido de resultados (balance de batería y diésel).
 * Son opcionales: los libros de 13 columnas las dejan en null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solar_data', function (Blueprint $table) {
            $table->double('estado_carga')->nullable()->after('lodos_finos');               // %
            $table->double('excedente_no_aprovechado')->nullable()->after('estado_carga'); // Wh acumulados
            $table->double('energia_diesel')->nullable()->after('excedente_no_aprovechado'); // Wh acumulados
            $table->double('combustible_diesel')->nullable()->after('energia_diesel');       // L acumulados
            $table->double('demanda_no_cubierta')->nullable()->after('combustible_diesel');  // Wh acumulados
        });
    }

    public function down(): void
    {
        Schema::table('solar_data', function (Blueprint $table) {
            $table->dropColumn(['estado_carga', 'excedente_no_aprovechado', 'energia_diesel', 'combustible_diesel', 'demanda_no_cubierta']);
        });
    }
};
