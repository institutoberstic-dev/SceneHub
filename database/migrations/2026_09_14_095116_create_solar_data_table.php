<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('solar_data', function (Blueprint $table) {
            $table->id();

            $table->foreignId('escenario_id')
                ->constrained('esceanarios')
                ->cascadeOnDelete();

            $table->foreignId('archivo_id')
                ->constrained('escenario_contenidos')
                ->cascadeOnDelete();

            // Hoja de origen: 1, 5 o 10 minutos.
            $table->unsignedTinyInteger('intervalo_minutos');

            // Tiempo transcurrido; permite superar las 24 horas.
            $table->unsignedInteger('tiempo_minutos');

            $table->double('caudal')->nullable();
            $table->double('radiacion_solar')->nullable();
            $table->double('temperatura')->nullable();
            $table->double('velocidad_viento')->nullable();
            $table->double('potencia_solar')->nullable();
            $table->double('potencia_neta')->nullable();
            $table->double('energia_almacenada')->nullable();
            $table->double('consumo_planta')->nullable();

            // Valores acumulados.
            $table->double('agua_desalinizada')->nullable();
            $table->double('salmuera')->nullable();
            $table->unsignedInteger('lodos_gruesos')->nullable();
            $table->unsignedInteger('lodos_finos')->nullable();

            $table->timestamps();

            $table->unique(
                ['archivo_id', 'intervalo_minutos', 'tiempo_minutos'],
                'solar_data_archivo_intervalo_tiempo_unique'
            );

            $table->index(
                ['escenario_id', 'intervalo_minutos', 'tiempo_minutos'],
                'solar_data_escenario_intervalo_tiempo_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solar_data');
    }
};
