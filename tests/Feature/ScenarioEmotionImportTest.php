<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ScenarioEmotionImport;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ScenarioEmotionImportTest extends TestCase
{
    use RefreshDatabase;

    private string $temporaryStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporaryStorage = sys_get_temp_dir().'/scenario-emotions-'.bin2hex(random_bytes(8));
        $this->app->useStoragePath($this->temporaryStorage);
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('cliente');
        $this->actingAs($user);
        Http::fake(['bersticlive.org/api/meetings' => Http::response(['meetings' => [['id' => 10, 'title' => 'Webinar de la API', 'topic' => 'Tema original']]])]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryStorage);
        parent::tearDown();
    }

    private function row(bool $average = false): array
    {
        $row = ['id_persona' => 41, 'id_meeting' => 10];
        foreach (ScenarioEmotionImport::METRICS as $metric) {
            $row[$metric.($average ? '_prom' : '')] = 0.1234567890123456;
        }

        return $average ? $row + ['emocion_prom' => 'happy'] : $row + [
            'archivo' => 'frame.jpg', 'nivel_atencion' => 'ATENTO', 'emocion_ganadora' => 'happy',
            'fecha' => 46233, 'tiempo' => '10:03:05', 'validez' => 'VALIDO', 'estatus_calidad_DAMA' => 'VERIFICADO',
        ];
    }

    private function excel(array $rows, string $name = 'arbitrary.xlsx'): UploadedFile
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([array_keys($rows[0]), ...array_map('array_values', $rows)], null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'emotion-test-');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
        $file = UploadedFile::fake()->createWithContent($name, file_get_contents($path));
        unlink($path);

        return $file;
    }

    private function createScenario(?UploadedFile $file = null): int
    {
        return $this->postJson('/escenarios-store', ['nombre' => 'Prueba', 'descripcion' => 'Emociones', 'archivo' => $file])
            ->assertCreated()->json('data.id');
    }

    public function test_create_upload_update_and_read_both_structures(): void
    {
        $id = $this->createScenario($this->excel([$this->row()]));
        $this->assertDatabaseHas('emotions', ['escenario_id' => $id, 'id_persona' => 41, 'validez' => 1, 'nivel_atencion' => 'ATENTO']);
        $this->assertEqualsWithDelta(0.1234567890123456, DB::table('emotions')->value('prob_happy'), 1e-14);
        $this->postJson("/escenarios/$id/contenidos", ['archivo' => $this->excel([$this->row(true)], 'other.xlsx')])->assertCreated();
        $this->assertDatabaseCount('emotions_prom', 1);
        $row = $this->row();
        $row['prob_happy'] = 0.75;
        $this->putJson("/escenarios/$id", ['nombre' => 'Prueba', 'descripcion' => 'Actualizado', 'estado' => 'Activo', 'archivo' => $this->excel([$row])])->assertOk();
        $this->assertDatabaseCount('emotions', 1);
        $this->assertDatabaseHas('emotions', ['prob_happy' => 0.75]);
        $this->assertFileExists($this->temporaryStorage."/app/public/escenarios/$id-prueba/1.0/arbitrary.xlsx");
        $this->assertFileExists($this->temporaryStorage."/app/public/escenarios/$id-prueba/1.1/arbitrary.xlsx");
        $this->getJson('/api/emociones')->assertOk()->assertJsonCount(1, 'meetings');
        $this->getJson('/api/emociones/10')->assertOk()->assertJsonPath('details.total', 1)->assertJsonPath('averages.total', 1);
    }

    public function test_bad_headers_and_bad_rows_do_not_create_scenario_or_files(): void
    {
        $row = $this->row();
        unset($row['id_persona']);
        $this->postJson('/escenarios-store', ['nombre' => 'Prueba', 'descripcion' => 'Test', 'archivo' => $this->excel([$row])])->assertUnprocessable()->assertJsonValidationErrors('archivo');
        $row = $this->row();
        $row['prob_happy'] = 1.1;
        $this->postJson('/escenarios-store', ['nombre' => 'Prueba', 'descripcion' => 'Test', 'archivo' => $this->excel([$this->row(), $row])])->assertUnprocessable()->assertJsonValidationErrors('archivo');
        $this->assertDatabaseCount('esceanarios', 0);
        $this->assertDatabaseCount('emotions', 0);
        $this->assertDirectoryDoesNotExist($this->temporaryStorage.'/app/public/escenarios');
    }

    public function test_replacing_source_removes_deleted_rows_and_unchanged_upload_does_not_duplicate_files(): void
    {
        $first = $this->row(true);
        $second = $first;
        $second['id_persona'] = 99;
        $file = $this->excel([$first, $second]);
        $id = $this->createScenario($file);
        $this->postJson("/escenarios/$id/contenidos", ['archivo' => $file])->assertOk();
        $this->assertDatabaseCount('escenario_contenidos', 1);
        $this->postJson("/escenarios/$id/contenidos", ['archivo' => $this->excel([$first])])->assertCreated();
        $this->assertDatabaseCount('emotions_prom', 1);
    }

    public function test_failed_update_preserves_data_and_file_version(): void
    {
        $id = $this->createScenario($this->excel([$this->row()]));
        $row = $this->row();
        $row['fecha'] = '2026-02-30';
        $this->putJson("/escenarios/$id", ['nombre' => 'Cambiado', 'descripcion' => 'Test', 'estado' => 'Activo', 'archivo' => $this->excel([$row])])->assertUnprocessable();
        $this->assertDatabaseHas('esceanarios', ['id' => $id, 'nombre' => 'Prueba', 'versiones' => 1]);
        $this->assertDatabaseCount('emotions', 1);
        $this->assertDirectoryDoesNotExist($this->temporaryStorage."/app/public/escenarios/$id-prueba/1.1");
    }

    public function test_no_detection_is_preserved_and_other_users_can_read(): void
    {
        $row = $this->row();
        $row['nivel_atencion'] = $row['emocion_ganadora'] = 'SIN DETECCION';
        $row['validez'] = 'NO VALIDO';
        $id = $this->createScenario($this->excel([$row]));
        $this->assertDatabaseHas('emotions', ['validez' => 0, 'emocion_ganadora' => 'SIN DETECCION']);
        $other = User::factory()->create();
        $other->assignRole('cliente');
        $this->actingAs($other)->getJson('/api/emociones')->assertOk()->assertJsonPath('meetings.0.title', 'Webinar de la API');
        $this->getJson('/api/emociones/10')->assertOk()->assertJsonPath('details.total', 1)->assertJsonPath('averages.total', 0);
        $this->getJson("/api/emociones/10?escenario_id=$id")->assertOk();
    }

    public function test_disk_failure_rolls_back_import_and_scenario(): void
    {
        File::partialMock()->shouldReceive('copy')->once()->andReturn(false);
        $this->postJson('/escenarios-store', ['nombre' => 'Prueba', 'descripcion' => 'Test', 'archivo' => $this->excel([$this->row()])])->assertStatus(500);
        $this->assertDatabaseCount('esceanarios', 0);
        $this->assertDatabaseCount('emotions', 0);
        $this->assertDirectoryDoesNotExist($this->temporaryStorage.'/app/public/escenarios/1-prueba');
    }

    public function test_duplicate_rows_and_disguised_workbooks_are_rejected(): void
    {
        $this->postJson('/escenarios-store', ['nombre' => 'Prueba', 'descripcion' => 'Test', 'archivo' => $this->excel([$this->row(), $this->row()])])->assertUnprocessable();
        $this->postJson('/escenarios-store', ['nombre' => 'Prueba', 'descripcion' => 'Test', 'archivo' => UploadedFile::fake()->createWithContent('fake.xlsx', 'not an Excel workbook')])->assertUnprocessable();
        $this->assertDatabaseCount('emotions', 0);
    }

    public function test_webinar_correlation_and_scenario_selection_do_not_mix_datasets(): void
    {
        $otherMeeting = $this->row();
        $otherMeeting['id_meeting'] = 20;
        $first = $this->createScenario($this->excel([$this->row(), $otherMeeting]));
        $second = $this->postJson('/escenarios-store', ['nombre' => 'Segundo', 'descripcion' => 'Test', 'archivo' => $this->excel([$this->row(true)])])->assertCreated()->json('data.id');
        $this->getJson('/api/emociones/10')->assertOk()->assertJsonCount(2, 'scenarios')->assertJsonPath('scenario.id', $second)->assertJsonPath('details.total', 0)->assertJsonPath('averages.total', 1);
        $this->getJson("/api/emociones/10?escenario_id=$first")->assertOk()->assertJsonPath('details.total', 1)->assertJsonPath('totals.records', 1)->assertJsonPath('averages.total', 0);
        $this->getJson('/api/emociones/20')->assertOk()->assertJsonCount(1, 'scenarios')->assertJsonPath('details.data.0.id_meeting', 20);
        $this->deleteJson("/escenarios/$first")->assertOk();
        $this->assertDatabaseCount('emotions', 0);
        $this->assertDatabaseCount('emotions_prom', 1);
    }
}
