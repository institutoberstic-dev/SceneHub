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
        Schema::create('emotions_prom', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_meeting');
            $table->decimal('score_atencion_prom', 10, 2);
            $table->decimal('prob_angry_prom', 10, 2);
            $table->decimal('prob_disgust_prom', 10, 2);
            $table->decimal('prob_fear_prom', 10, 2);
            $table->decimal('prob_happy_prom', 10, 2);
            $table->decimal('prob_neutral_prom', 10, 2);
            $table->decimal('prob_sad_prom', 10, 2);
            $table->decimal('prob_surprise_prom', 10, 2);
            $table->string('emocion_prom', 100);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emotions_prom');
    }
};
