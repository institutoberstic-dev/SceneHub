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
        Schema::create('features', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->decimal('corriente', 10,5);
            $table->decimal('temperatura', 10,5);
            $table->decimal('presion', 10,5);
            $table->decimal('eficiencia', 10,5);
            $table->decimal('voltajeTotal', 10,5);
            $table->decimal('produccionHidrogeno', 10,6);
            $table->decimal('temperaturaAmbiente', 10,5);
            $table->decimal('coefConvectivo', 10,5);
            $table->decimal('resistenciaInterna', 10,5);
            $table->integer('numCeldas');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('features');
    }
};
