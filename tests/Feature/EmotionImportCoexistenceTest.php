<?php

namespace Tests\Feature;

use App\Services\ScenarioEmotionImport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmotionImportCoexistenceTest extends TestCase
{
    public function test_uploads_preserve_the_other_format_and_failed_replacements_preserve_data(): void
    {
        // Isolated storage contract test: never touch the application's database.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        foreach (['emotions', 'emotions_prom'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_meeting');
                $table->string('source_file');
                $table->string('record_key');
                $table->timestamps();
            });
        }
        $service = app(ScenarioEmotionImport::class);
        $import = fn ($table, $file) => ['table' => $table, 'source_file' => $file, 'rows' => [['id_meeting' => 10]]];
        $service->persist(10, $import('emotions', 'completa.xlsx'));
        $service->persist(10, $import('emotions_prom', 'promedio.xlsx'));
        $service->persist(10, $import('emotions', 'completa-nueva.xlsx'));
        $this->assertDatabaseHas('emotions_prom', ['id_meeting' => 10, 'source_file' => 'promedio.xlsx']);
        $this->assertDatabaseHas('emotions', ['id_meeting' => 10, 'source_file' => 'completa-nueva.xlsx']);
        $this->assertDatabaseCount('emotions', 1);
        $bad = $import('emotions_prom', 'fallido.xlsx');
        $bad['rows'][0]['unknown_column'] = 'invalid';
        try {
            $service->persist(10, $bad);
            $this->fail('The invalid insert must fail.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertDatabaseHas('emotions_prom', ['source_file' => 'promedio.xlsx']);
        }
    }
}
