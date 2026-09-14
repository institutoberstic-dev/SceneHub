<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escenario_id')->constrained('esceanarios')->cascadeOnDelete();
            $table->foreignId('archivo_id')->constrained('escenario_contenidos')->cascadeOnDelete();
            $table->string('tipo', 30);
            $table->unsignedInteger('revision');
            $table->string('version', 30);
            $table->char('sha256', 64);
            $table->timestamps();
            $table->unique(['escenario_id', 'tipo', 'revision']);
        });
        Schema::table('emotions_prom', function (Blueprint $table) {
            $table->foreignId('data_version_id')->nullable()->constrained('data_versions')->cascadeOnDelete();
            $table->string('nivel_atencion_prom', 100)->nullable();
            $table->string('emocion_ganadora_prom', 100)->nullable();
            foreach (['score_atencion', 'prob_angry', 'prob_disgust', 'prob_fear', 'prob_happy', 'prob_neutral', 'prob_sad', 'prob_surprise'] as $metric) {
                $table->decimal($metric.'_prom', 18, 16)->nullable()->change();
            }
            $table->string('emocion_prom', 100)->nullable()->change();
            $table->unique(['data_version_id', 'id_meeting']);
        });
        // Register existing solar imports so Unity can discover them immediately.
        $revisions = [];
        foreach (DB::table('escenario_contenidos')->whereIn('id', DB::table('solar_data')->select('archivo_id'))->orderBy('id')->get() as $file) {
            $revision = ($revisions[$file->escenario_id] ?? 9) + 1;
            $revisions[$file->escenario_id] = $revision;
            $rows = DB::table('solar_data')->where('archivo_id', $file->id)->orderBy('intervalo_minutos')->orderBy('tiempo_minutos')->get()->map(function ($row) {
                $data = (array) $row;
                foreach (['id', 'archivo_id', 'escenario_id', 'created_at', 'updated_at'] as $key) unset($data[$key]);
                ksort($data);
                return $data;
            })->all();
            DB::table('data_versions')->insert(['escenario_id' => $file->escenario_id, 'archivo_id' => $file->id, 'tipo' => 'solar', 'revision' => $revision, 'version' => intdiv($revision, 10).'.'.($revision % 10), 'sha256' => hash('sha256', json_encode($rows)), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('emotions_prom', function (Blueprint $table) {
            $table->dropUnique(['data_version_id', 'id_meeting']);
            $table->dropConstrainedForeignId('data_version_id');
            $table->dropColumn(['nivel_atencion_prom', 'emocion_ganadora_prom']);
        });
        Schema::dropIfExists('data_versions');
        // Keep legacy fields nullable: new source files do not contain these values.
    }
};
