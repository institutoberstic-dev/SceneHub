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
        Schema::create('simu_solars', function (Blueprint $table) {
            $table->id();
            $table->string('simulationRunning');
            $table->decimal('inclinacion', 10,5);
            $table->decimal('direccion', 10,5);
            $table->decimal('latitud', 10,5);
            $table->decimal('inclinacionOptima', 10,5);
            $table->string('estado');
            $table->decimal('radiacion', 10,5);
            $table->decimal('perdidas', 10,5);
            $table->decimal('generacion', 10,5);
            $table->decimal('conexion', 10,5);
            $table->decimal('temperatura', 10,5);
            $table->decimal('voltaje', 10,5);
            $table->decimal('corriente', 10,5);
            $table->decimal('electrolizacion', 10,5);
            $table->integer('bateria');
            $table->decimal('consumo', 10,5);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simu_solars');
    }
};
