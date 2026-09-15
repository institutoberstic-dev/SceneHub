<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emotions_prom', function (Blueprint $table): void {
            foreach ([
                'score_atencion_prom',
                'prob_angry_prom',
                'prob_disgust_prom',
                'prob_fear_prom',
                'prob_happy_prom',
                'prob_neutral_prom',
                'prob_sad_prom',
                'prob_surprise_prom',
                'emocion_prom',
                'id_persona',
            ] as $column) {
                if (Schema::hasColumn('emotions_prom', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('emotions_prom', function (Blueprint $table): void {
            $table->decimal('score_atencion_prom', 18, 16)->nullable();
            foreach (['angry', 'disgust', 'fear', 'happy', 'neutral', 'sad', 'surprise'] as $emotion) {
                $table->decimal('prob_'.$emotion.'_prom', 18, 16)->nullable();
            }
            $table->string('emocion_prom', 100)->nullable();
            $table->unsignedBigInteger('id_persona')->nullable();
        });
    }
};
