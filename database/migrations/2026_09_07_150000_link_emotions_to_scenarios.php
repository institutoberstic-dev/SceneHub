<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['emotions', 'emotions_prom'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->foreignId('escenario_id')->nullable()->constrained('esceanarios')->cascadeOnDelete();
                // These identifiers belong to the source system, not local application users.
                $table->unsignedBigInteger('id_persona')->nullable();
                $table->string('source_file')->nullable();
                $table->char('record_key', 64)->nullable();
                $table->unique(['escenario_id', 'record_key']);
                $table->index(['escenario_id', 'id_meeting']);
                $suffix = $name === 'emotions_prom' ? '_prom' : '';
                foreach (['score_atencion', 'prob_angry', 'prob_disgust', 'prob_fear', 'prob_happy', 'prob_neutral', 'prob_sad', 'prob_surprise'] as $metric) {
                    $table->decimal($metric.$suffix, 18, 16)->change();
                }
                if ($name === 'emotions') {
                    $table->string('nivel_atencion', 30)->change();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['emotions', 'emotions_prom'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropUnique(['escenario_id', 'record_key']);
                $table->dropIndex(['escenario_id', 'id_meeting']);
                $table->dropForeign(['escenario_id']);
                $table->dropColumn(['escenario_id', 'id_persona', 'source_file', 'record_key']);
            });
        }
        // Keep the wider metric/text types to avoid lossy rollback conversions.
    }
};
