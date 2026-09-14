<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class EmotionVersionsTest extends TestCase
{
    use RefreshDatabase;
    private string $temp;
    protected function setUp(): void
    {
        parent::setUp();
        $this->temp = sys_get_temp_dir().'/emotion-version-'.bin2hex(random_bytes(8));
        $this->app->useStoragePath($this->temp);
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(); $user->assignRole('cliente'); $this->actingAs($user);
        Http::fake(['bersticlive.org/api/meetings' => Http::response(['meetings' => []])]);
    }
    protected function tearDown(): void
    {
        File::deleteDirectory($this->temp);
        parent::tearDown();
    }
    private function file(string $emotion = 'disgust', string $name = 'promedio.xlsx'): UploadedFile
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([['id_meeting', 'nivel_atencion_prom', 'emocion_ganadora_prom'], [10, 'ATENTO', $emotion]]);
        $path = tempnam(sys_get_temp_dir(), 'average-');
        (new Xlsx($book))->save($path);
        $file = UploadedFile::fake()->createWithContent($name, file_get_contents($path));
        unlink($path); $book->disconnectWorksheets();
        return $file;
    }
    public function test_raw_average_and_version_lifecycle(): void
    {
        $id = $this->postJson('/escenarios-store', ['nombre' => 'Promedio', 'descripcion' => 'Test', 'archivo' => $this->file()])->assertCreated()->json('data.id');
        $base = "/api/escenarios/$id";
        $this->assertDatabaseHas('emotions_prom', ['id_meeting' => 10, 'nivel_atencion_prom' => 'ATENTO', 'emocion_ganadora_prom' => 'disgust', 'score_atencion_prom' => null]);
        $this->getJson("$base/versiones-datos?tipo=emociones_promedio")->assertOk()->assertJsonPath('estado', 'descarga_inicial')->assertJsonPath('version', '1.0');
        $this->getJson("$base/emociones-promedio?version=1.0")->assertOk()->assertExactJson(['escenario_id' => $id, 'tipo' => 'emociones_promedio', 'version' => '1.0', 'sha256' => DB::table('data_versions')->value('sha256'), 'hay_datos' => true, 'datos' => [['id_meeting' => 10, 'nivel_atencion_prom' => 'ATENTO', 'emocion_ganadora_prom' => 'disgust']]]);
        $this->getJson("$base/versiones-datos?tipo=emociones_promedio&version_actual=1.0")->assertJsonPath('estado', 'sin_cambios')->assertJsonPath('hay_datos', true);
        $this->postJson("/escenarios/$id/contenidos", ['archivo' => $this->file('disgust', 'otro-nombre.xlsx')])->assertCreated();
        $this->assertDatabaseCount('data_versions', 1);
        $this->postJson("/escenarios/$id/contenidos", ['archivo' => $this->file('happy')])->assertCreated();
        $this->assertDatabaseCount('data_versions', 2);
        $this->getJson("$base/versiones-datos?tipo=emociones_promedio&version_actual=1.0")->assertJsonPath('estado', 'actualizacion_disponible')->assertJsonPath('version', '1.1');
        $this->getJson("$base/emociones-promedio?version=1.0")->assertJsonPath('datos.0.emocion_ganadora_prom', 'disgust');
        $this->getJson("$base/emociones-promedio?version=1.1")->assertJsonPath('datos.0.emocion_ganadora_prom', 'happy');
        $this->getJson("$base/versiones-datos?tipo=solar")->assertJsonPath('estado', 'sin_datos');
        $this->getJson("$base/versiones-datos?tipo=emociones_promedio&version_actual=2.0")->assertJsonPath('estado', 'version_local_posterior');
        $this->getJson('/api/emociones')->assertJsonPath('meetings.0.id', 10);
        $this->getJson('/api/emociones/10')->assertJsonPath('summary.0.emocion_ganadora_prom', 'happy');
        $this->getJson('/api/emociones/10?version=1.0')->assertJsonPath('summary.0.emocion_ganadora_prom', 'disgust');
        $other = User::factory()->create(); $other->assignRole('cliente'); $this->actingAs($other);
        foreach (['versiones-datos?tipo=emociones_promedio', 'emociones-promedio', 'solar'] as $path) $this->getJson("$base/$path")->assertForbidden();
    }
    public function test_bad_average_is_rejected_without_advancing_version(): void
    {
        $id = $this->postJson('/escenarios-store', ['nombre' => 'Promedio', 'descripcion' => 'Test', 'archivo' => $this->file()])->assertCreated()->json('data.id');
        $this->postJson("/escenarios/$id/contenidos", ['archivo' => $this->file('')])->assertUnprocessable();
        $this->assertDatabaseCount('data_versions', 1);
        $this->assertDatabaseCount('emotions_prom', 1);
    }

    public function test_scenario_overview_and_update_api_expose_versions(): void
    {
        $id = $this->postJson('/escenarios-store', ['nombre' => 'API', 'descripcion' => 'Test', 'archivo' => $this->file()])->assertCreated()->json('data.id');
        $overview = $this->getJson("/api/escenarios/$id/datos")->assertOk();
        $overview->assertJsonPath('escenario.id', $id)
            ->assertJsonPath('datos.emociones_promedio.disponible', true)
            ->assertJsonPath('datos.emociones_promedio.version', '1.0')
            ->assertJsonPath('datos.solar.disponible', false)
            ->assertJsonPath('metodo_actualizacion', 'POST multipart/form-data con archivo y nombre opcional');

        $this->post("/api/escenarios/$id/datos/actualizar", ['archivo' => $this->file('disgust', 'promedio.xlsx')])
            ->assertOk()->assertJsonPath('stored', false)->assertJsonPath('new_version', false);
        $this->post("/api/escenarios/$id/datos/actualizar", ['archivo' => $this->file('happy', 'promedio.xlsx')])
            ->assertCreated()->assertJsonPath('stored', true)->assertJsonPath('new_version', true);
        $this->getJson("/api/escenarios/$id/datos")->assertJsonPath('datos.emociones_promedio.version', '1.1');

        $other = User::factory()->create();
        $other->assignRole('cliente');
        $this->actingAs($other);
        $this->getJson("/api/escenarios/$id/datos")->assertForbidden();
        $this->post("/api/escenarios/$id/datos/actualizar", ['archivo' => $this->file()])->assertForbidden();
    }

    public function test_reference_workbook_when_provided(): void
    {
        $path = getenv('EMOTION_SAMPLE_FILE');
        if (! $path || ! is_file($path)) $this->markTestSkipped('Archivo original opcional no proporcionado.');
        $file = UploadedFile::fake()->createWithContent('promedio-original.xlsx', file_get_contents($path));
        $id = $this->postJson('/escenarios-store', ['nombre' => 'Referencia', 'descripcion' => 'Prueba aislada', 'archivo' => $file])->assertCreated()->json('data.id');
        $this->getJson("/api/escenarios/$id/emociones-promedio")->assertOk()->assertJsonCount(1, 'datos')->assertJsonPath('datos.0.id_meeting', 10)->assertJsonPath('datos.0.nivel_atencion_prom', 'ATENTO')->assertJsonPath('datos.0.emocion_ganadora_prom', 'disgust');
    }
}
