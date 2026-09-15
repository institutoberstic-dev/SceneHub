<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Emotional data belongs to a webinar, never to a solar scenario.
        foreach (['emotions', 'emotions_prom'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (Schema::hasColumn($tableName, 'escenario_id')) {
                    $table->dropUnique(['escenario_id', 'record_key']);
                    $table->dropForeign(['escenario_id']);
                    $table->dropIndex(['escenario_id', 'id_meeting']);
                    $table->dropColumn('escenario_id');
                }
            });
        }

        DB::table('data_versions')->where('tipo', 'emociones_promedio')->delete();
        Schema::table('emotions_prom', function (Blueprint $table): void {
            if (Schema::hasColumn('emotions_prom', 'data_version_id')) {
                $table->dropForeign(['data_version_id']);
                $table->dropUnique(['data_version_id', 'id_meeting']);
                $table->dropColumn('data_version_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('emotions', function (Blueprint $table): void {
            $table->foreignId('escenario_id')->nullable()->constrained('esceanarios')->cascadeOnDelete();
        });
        Schema::table('emotions_prom', function (Blueprint $table): void {
            $table->foreignId('escenario_id')->nullable()->constrained('esceanarios')->cascadeOnDelete();
            $table->foreignId('data_version_id')->nullable()->constrained('data_versions')->cascadeOnDelete();
        });
    }
};
