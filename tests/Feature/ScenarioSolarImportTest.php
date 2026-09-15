<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ScenarioSolarImport;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ScenarioSolarImportTest extends TestCase
{
    use RefreshDatabase;

    private string $temporaryStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporaryStorage = sys_get_temp_dir().'/solar-test-'.bin2hex(random_bytes(8));
        $this->app->useStoragePath($this->temporaryStorage);
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('cliente');
        $this->actingAs($user);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryStorage);
        parent::tearDown();
    }

    private function excel(bool $missing = false, bool $bad = false, float $power = 0): UploadedFile
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);
        foreach (['Minutos' => 1, 'Cada5min' => 5, 'Cada10min' => 10, 'Horas' => 60] as $name => $interval) {
            if ($missing && $interval === 10) {
                continue;
            }
            $sheet = $book->createSheet()->setTitle($name);
            $row = [$name === 'Horas' ? 1 : $interval, 11.66666, 0, 24.1, 6.5, $power, -3, 500000, 3, 0, 0, 0, 0];
            if ($bad) {
                $row[5] = '=1+1';
            }
            $sheet->fromArray([ScenarioSolarImport::HEADERS, $row], null, 'A1', true);
        }
        $path = tempnam(sys_get_temp_dir(), 'solar-');
        (new Xlsx($book))->save($path);
        $file = UploadedFile::fake()->createWithContent(ScenarioSolarImport::OFFICIAL_FILENAME, file_get_contents($path));
        unlink($path);
        $book->disconnectWorksheets();

        return $file;
    }

    public function test_create_read_reupload_update_and_permissions(): void
    {
        $file = $this->excel();
        $id = $this->postJson('/escenarios-store', ['nombre' => 'Solar', 'descripcion' => 'Test', 'archivo' => $file])->assertCreated()->json('data.id');
        $this->assertDatabaseCount('solar_data', 4);
        $this->assertDatabaseCount('emotions', 0);
        $this->assertEqualsWithDelta(11.66666, DB::table('solar_data')->value('caudal'), 1e-9);
        $url = "/api/escenarios/$id/resultados-solares";
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'archivos')
            ->assertJsonPath('archivos.0.muestreos.5.0.tiempo_minutos', 5)
            ->assertJsonPath('archivos.0.muestreos.1.0.energia_almacenada_wh', null)
            ->assertJsonPath('archivos.0.muestreos.1.0.energia_almacenada_original', 500000);
        $this->postJson("/escenarios/$id/contenidos", ['archivo' => $file])->assertOk();
        $this->assertDatabaseCount('solar_data', 4);
        $this->putJson("/escenarios/$id", ['nombre' => 'Solar', 'descripcion' => 'Actualizado', 'estado' => 'Activo', 'archivo' => $file])->assertOk();
        $this->assertDatabaseCount('solar_data', 4);
        $renamed = UploadedFile::fake()->createWithContent('renombrado.xlsx', file_get_contents($file->getRealPath()));
        $this->postJson("/escenarios/$id/contenidos", ['archivo' => $renamed])->assertUnprocessable();
        $this->assertDatabaseCount('solar_data', 4);
        $this->assertDatabaseCount('escenario_contenidos', 1);
        $other = User::factory()->create();
        $other->assignRole('cliente');
        $this->actingAs($other)->getJson($url)->assertOk();
    }

    public function test_invalid_workbooks_and_non_excel_are_rejected(): void
    {
        foreach ([$this->excel(true), $this->excel(false, true)] as $file) {
            $this->postJson('/escenarios-store', ['nombre' => 'Solar', 'descripcion' => 'Test', 'archivo' => $file])->assertUnprocessable();
            $this->assertDatabaseCount('esceanarios', 0);
        }
        $this->postJson('/escenarios-store', ['nombre' => 'Solar', 'descripcion' => 'Test', 'archivo' => UploadedFile::fake()->createWithContent('nota.txt', 'hello')])->assertUnprocessable();
        $this->assertDatabaseCount('solar_data', 0);
    }

    public function test_restoring_an_older_result_publishes_a_new_variation(): void
    {
        $original = $this->excel();
        $id = $this->postJson('/escenarios-store', ['nombre' => 'Variante solar', 'descripcion' => 'Test', 'archivo' => $original])->assertCreated()->json('data.id');
        $this->postJson("/escenarios/$id/contenidos", ['archivo' => $this->excel(power: 20)])->assertCreated();
        $this->postJson("/escenarios/$id/contenidos", ['archivo' => $original])->assertCreated();
        $this->assertDatabaseCount('data_versions', 3);
        foreach (['1.0' => 0, '1.1' => 20, '1.2' => 0] as $version => $expected) {
            $response = $this->getJson("/api/escenarios/$id/solar?version=$version")->assertOk();
            $this->assertEquals($expected, $response->json('muestreos.1.0.potencia_solar'));
        }
        $this->getJson("/api/escenarios/$id/versiones-datos?tipo=solar&version_actual=1.1")
            ->assertJsonPath('actualizacion_disponible', true)->assertJsonPath('version', '1.2');
    }

    public function test_original_workbook_when_provided(): void
    {
        $path = getenv('SOLAR_SAMPLE_FILE');
        if (! $path || ! is_file($path)) {
            $this->markTestSkipped('Archivo original opcional no proporcionado.');
        }
        $file = UploadedFile::fake()->createWithContent(ScenarioSolarImport::OFFICIAL_FILENAME, file_get_contents($path));
        $id = $this->postJson('/escenarios-store', ['nombre' => 'Original', 'descripcion' => 'Validación aislada', 'archivo' => $file])->assertCreated()->json('data.id');
        $this->assertDatabaseCount('solar_data', 2370);
        $response = $this->getJson("/api/escenarios/$id/resultados-solares")->assertOk();
        $response->assertJsonCount(1800, 'archivos.0.muestreos.1')->assertJsonCount(360, 'archivos.0.muestreos.5')->assertJsonCount(180, 'archivos.0.muestreos.10');
        $this->getJson("/api/escenarios/$id/versiones-datos?tipo=solar")->assertOk()->assertJsonPath('estado', 'descarga_inicial')->assertJsonPath('version', '1.0');
        $this->getJson("/api/escenarios/$id/solar?version=1.0")->assertOk()->assertJsonCount(1800, 'muestreos.1')->assertJsonCount(360, 'muestreos.5')->assertJsonCount(180, 'muestreos.10');
        $this->getJson("/api/escenarios/$id/versiones-datos?tipo=solar&version_actual=1.0")->assertJsonPath('estado', 'sin_cambios');
    }
}
