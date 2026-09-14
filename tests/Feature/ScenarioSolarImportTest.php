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

    private function excel(bool $missing = false, bool $bad = false): UploadedFile
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);
        foreach (['Minutos' => 1, 'Cada5min' => 5, 'Cada10min' => 10] as $name => $interval) {
            if ($missing && $interval === 10) continue;
            $sheet = $book->createSheet()->setTitle($name);
            $row = [$interval, 11.66666, 0, 24.1, 6.5, 0, -3, 500000, 3, 0, 0, 0, 0];
            if ($bad) $row[5] = '=1+1';
            $sheet->fromArray([ScenarioSolarImport::HEADERS, $row], null, 'A1', true);
        }
        $book->createSheet()->setTitle('Horas')->setCellValue('A1', 'Esta hoja no se importa');
        $path = tempnam(sys_get_temp_dir(), 'solar-');
        (new Xlsx($book))->save($path);
        $file = UploadedFile::fake()->createWithContent('nombre-libre.xlsx', file_get_contents($path));
        unlink($path);
        $book->disconnectWorksheets();
        return $file;
    }

    public function test_create_read_reupload_update_and_permissions(): void
    {
        $file = $this->excel();
        $id = $this->postJson('/escenarios-store', ['nombre' => 'Solar', 'descripcion' => 'Test', 'archivo' => $file])->assertCreated()->json('data.id');
        $this->assertDatabaseCount('solar_data', 3);
        $this->assertDatabaseCount('emotions', 0);
        $this->assertEqualsWithDelta(11.66666, DB::table('solar_data')->value('caudal'), 1e-9);
        $url = "/api/escenarios/$id/resultados-solares";
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'archivos')
            ->assertJsonPath('archivos.0.muestreos.5.0.tiempo_minutos', 5)
            ->assertJsonPath('archivos.0.muestreos.1.0.energia_almacenada_wh', null)
            ->assertJsonPath('archivos.0.muestreos.1.0.energia_almacenada_original', 500000);
        $this->postJson("/escenarios/$id/contenidos", ['archivo' => $file])->assertOk();
        $this->assertDatabaseCount('solar_data', 3);
        $this->putJson("/escenarios/$id", ['nombre' => 'Solar', 'descripcion' => 'Actualizado', 'estado' => 'Activo', 'archivo' => $file])->assertOk();
        $this->assertDatabaseCount('solar_data', 3);
        $renamed = UploadedFile::fake()->createWithContent('renombrado.xlsx', file_get_contents($file->getRealPath()));
        $this->postJson("/escenarios/$id/contenidos", ['archivo' => $renamed])->assertOk();
        $this->assertDatabaseCount('solar_data', 3);
        $this->assertDatabaseCount('escenario_contenidos', 1);
        $other = User::factory()->create();
        $other->assignRole('cliente');
        $this->actingAs($other)->getJson($url)->assertForbidden();
    }

    public function test_invalid_workbooks_roll_back_and_non_excel_still_uploads(): void
    {
        foreach ([$this->excel(true), $this->excel(false, true)] as $file) {
            $this->postJson('/escenarios-store', ['nombre' => 'Solar', 'descripcion' => 'Test', 'archivo' => $file])->assertUnprocessable();
            $this->assertDatabaseCount('esceanarios', 0);
        }
        $id = $this->postJson('/escenarios-store', ['nombre' => 'Solar', 'descripcion' => 'Test', 'archivo' => UploadedFile::fake()->createWithContent('nota.txt', 'hello')])->assertCreated()->json('data.id');
        $this->postJson("/escenarios/$id/contenidos", ['archivo' => $this->excel()])->assertCreated();
        $this->assertDatabaseCount('solar_data', 3);
    }

    public function test_original_workbook_when_provided(): void
    {
        $path = getenv('SOLAR_SAMPLE_FILE');
        if (! $path || ! is_file($path)) $this->markTestSkipped('Archivo original opcional no proporcionado.');
        $file = UploadedFile::fake()->createWithContent('original.xlsx', file_get_contents($path));
        $id = $this->postJson('/escenarios-store', ['nombre' => 'Original', 'descripcion' => 'Validación aislada', 'archivo' => $file])->assertCreated()->json('data.id');
        $this->assertDatabaseCount('solar_data', 2340);
        $response = $this->getJson("/api/escenarios/$id/resultados-solares")->assertOk();
        $response->assertJsonCount(1800, 'archivos.0.muestreos.1')->assertJsonCount(360, 'archivos.0.muestreos.5')->assertJsonCount(180, 'archivos.0.muestreos.10');
        $this->getJson("/api/escenarios/$id/versiones-datos?tipo=solar")->assertOk()->assertJsonPath('estado', 'descarga_inicial')->assertJsonPath('version', '1.0');
        $this->getJson("/api/escenarios/$id/solar?version=1.0")->assertOk()->assertJsonCount(1800, 'muestreos.1')->assertJsonCount(360, 'muestreos.5')->assertJsonCount(180, 'muestreos.10');
        $this->getJson("/api/escenarios/$id/versiones-datos?tipo=solar&version_actual=1.0")->assertJsonPath('estado', 'sin_cambios');
    }
}
