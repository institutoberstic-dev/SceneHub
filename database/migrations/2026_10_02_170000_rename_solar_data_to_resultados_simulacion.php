<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La tabla guarda la serie completa de la simulación (clima, energía, agua y
 * residuos), no solo datos solares. Solo cambia el nombre: los datos, índices y
 * llaves foráneas se conservan. Las versiones de datos pasan de `solar` a
 * `simulacion`; la API sigue aceptando `tipo=solar` por compatibilidad con Unity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('solar_data', 'resultados_simulacion');
        DB::table('data_versions')->where('tipo', 'solar')->update(['tipo' => 'simulacion']);
    }

    public function down(): void
    {
        DB::table('data_versions')->where('tipo', 'simulacion')->update(['tipo' => 'solar']);
        Schema::rename('resultados_simulacion', 'solar_data');
    }
};
