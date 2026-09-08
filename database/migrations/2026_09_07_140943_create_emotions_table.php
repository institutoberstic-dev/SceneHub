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
        Schema::create('emotions', function (Blueprint $table) {
            $table->id();
            // $table->unsignedBigInteger('id_persona');
            $table->unsignedBigInteger('id_meeting');
            $table->string('archivo', 255);
            $table->decimal('nivel_atencion', 10, 2);
            $table->decimal('score_atencion', 10, 2);
            $table->string('emocion_ganadora', 100);
            $table->decimal('prob_angry', 10, 2);
            $table->decimal('prob_disgust', 10, 2);
            $table->decimal('prob_fear', 10, 2);
            $table->decimal('prob_happy', 10, 2);
            $table->decimal('prob_neutral', 10, 2);
            $table->decimal('prob_sad', 10, 2);
            $table->decimal('prob_surprise', 10, 2);
            $table->date('fecha');
            $table->time('tiempo');
            $table->boolean('validez')->default(false);
            $table->string('estatus_calidad_DAMA', 100);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emotions');
    }
};
