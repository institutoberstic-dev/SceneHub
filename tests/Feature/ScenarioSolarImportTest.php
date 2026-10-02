<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SimulationResultsImport;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;
use ZipArchive;

class ScenarioSolarImportTest extends TestCase
{
    use RefreshDatabase;

    private const SHEETS = ['Minutos' => 1, 'Cada5min' => 5, 'Cada10min' => 10, 'Horas' => 60];

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

    /** @param array<string, array<int, array<int, mixed>>> $sheets nombre de hoja => filas (la primera es el encabezado) */
    private function workbook(array $sheets, string $name = SimulationResultsImport::OFFICIAL_FILENAME): UploadedFile
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);
        foreach ($sheets as $title => $rows) {
            $book->createSheet()->setTitle($title)->fromArray($rows, null, 'A1', true);
        }
        $path = tempnam(sys_get_temp_dir(), 'solar-');
        (new Xlsx($book))->save($path);
        $file = UploadedFile::fake()->createWithContent($name, file_get_contents($path));
        unlink($path);
        $book->disconnectWorksheets();

        return $file;
    }

    /** Libro de resultados con una fila por hoja; $tweak permite alterar cada fila. */
    private function excel(bool $missing = false, ?callable $tweak = null, float $power = 0, bool $extended = false, string $name = SimulationResultsImport::OFFICIAL_FILENAME): UploadedFile
    {
        $sheets = [];
        foreach (self::SHEETS as $title => $interval) {
            if ($missing && $interval === 10) {
                continue;
            }
            $row = [$title === 'Horas' ? 1 : $interval, 11.66666, 0, 24.1, 6.5, $power, -3, 500000, 3, 0, 0, 0, 0];
            $headers = SimulationResultsImport::HEADERS;
            if ($extended) {
                $headers = SimulationResultsImport::EXTENDED_HEADERS;
                array_push($row, 100, 1500.5, 152.12, 0.0456, 0);
            }
            if ($tweak) {
                $row = $tweak($row, $title);
            }
            $sheets[$title] = [$headers, $row];
        }

        return $this->workbook($sheets, $name);
    }

    private function word(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'word-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Anexo</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();
        $file = UploadedFile::fake()->createWithContent('manual.docx', file_get_contents($path));
        unlink($path);

        return $file;
    }

    /** Libro de contexto (datos de la comunidad): no es un resultado de simulación. */
    private function contextWorkbook(): UploadedFile
    {
        return $this->workbook([
            'Datos' => [['Datos comunidad', null, null], ['población colegio', 700, 'personas']],
            'Hoja1' => [['Componente', 'Parámetro', 'Valor'], ['Paneles solares', 'Cantidad', '120 paneles']],
        ], 'Datos comunidad 1.xlsx');
    }

    public function test_create_read_reupload_update_and_permissions(): void
    {
        $file = $this->excel();
        $id = $this->postJson('/escenarios-store', ['nombre' => 'Solar', 'descripcion' => 'Test', 'archivo' => $file])->assertCreated()->json('data.id');
        $this->assertDatabaseCount('resultados_simulacion', 4);
        $this->assertDatabaseCount('emotions', 0);
        $this->assertEqualsWithDelta(11.66666, DB::table('resultados_simulacion')->value('caudal'), 1e-9);
        $url = "/api/escenarios/$id/resultados-solares";
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'archivos')
            ->assertJsonPath('archivos.0.muestreos.5.0.tiempo_minutos', 5)
            ->assertJsonPath('archivos.0.muestreos.60.0.tiempo_minutos', 60)
            ->assertJsonPath('archivos.0.muestreos.1.0.energia_almacenada_wh', 500000)
            ->assertJsonPath('archivos.0.muestreos.1.0.energia_almacenada_original', 500000)
            ->assertJsonPath('archivos.0.muestreos.1.0.estado_carga_pct', null)
            ->assertJsonPath('archivos.0.advertencias', []);
        $this->getJson("/escenarios-data/$id/resultados")->assertOk()->assertJsonPath('archivos.0.nombre', SimulationResultsImport::OFFICIAL_FILENAME);
        $this->postJson("/escenarios/$id/contenidos", ['archivo' => $file])->assertOk();
        $this->assertDatabaseCount('resultados_simulacion', 4);
        $this->putJson("/escenarios/$id", ['nombre' => 'Solar', 'descripcion' => 'Actualizado', 'estado' => 'Activo', 'archivo' => $file])->assertOk();
        $this->assertDatabaseCount('resultados_simulacion', 4);
        $renamed = UploadedFile::fake()->createWithContent('renombrado.xlsx', file_get_contents($file->getRealPath()));
        $this->postJson("/escenarios/$id/contenidos", ['archivo' => $renamed])->assertCreated();
        $this->assertDatabaseCount('resultados_simulacion', 8);
        $this->assertDatabaseCount('escenario_contenidos', 2);
        $other = User::factory()->create();
        $other->assignRole('cliente');
        $this->actingAs($other)->getJson($url)->assertOk();
    }

    public function test_extended_results_store_the_energy_balance(): void
    {
        $file = $this->excel(extended: true, name: 'resultados escenario 1 1.xlsx');
        $response = $this->postJson('/escenarios-store', ['nombre' => 'Escenario 1', 'descripcion' => 'Producción de agua', 'archivo' => $file])
            ->assertCreated()
            ->assertJsonPath('data.contenidos.0.tipo', 'datos');
        $id = $response->json('data.id');
        $this->assertStringContainsString('importado a resultados', $response->json('message'));

        $row = DB::table('resultados_simulacion')->where('intervalo_minutos', 1)->first();
        $this->assertEquals(100, $row->estado_carga);
        $this->assertEqualsWithDelta(1500.5, $row->excedente_no_aprovechado, 1e-9);
        $this->assertEqualsWithDelta(152.12, $row->energia_diesel, 1e-9);
        $this->assertEqualsWithDelta(0.0456, $row->combustible_diesel, 1e-9);
        $this->assertEquals(0, $row->demanda_no_cubierta);
        $this->assertEqualsWithDelta(24.1, $row->temperatura, 1e-9);

        $results = $this->getJson("/escenarios-data/$id/resultados")->assertOk()
            ->assertJsonPath('archivos.0.muestreos.1.0.estado_carga_pct', 100)
            ->assertJsonPath('archivos.0.muestreos.1.0.combustible_diesel_acum_l', 0.0456)
            ->assertJsonPath('archivos.0.muestreos.60.0.energia_diesel_acum_wh', 152.12);
        $this->assertContains('estado_carga_pct', $results->json('archivos.0.variables_disponibles'));
        $this->assertContains('demanda_no_cubierta_acum_wh', $results->json('archivos.0.variables_disponibles'));

        $this->getJson("/api/escenarios/$id/solar")->assertOk()
            ->assertJsonPath('unidades.estado_carga', '%')
            ->assertJsonPath('unidades.energia_almacenada', 'Wh')
            ->assertJsonPath('unidades.combustible_diesel', 'L')
            ->assertJsonPath('muestreos.1.0.estado_carga', 100)
            ->assertJsonPath('advertencias', []);
    }

    public function test_basic_results_only_report_the_variables_they_contain(): void
    {
        $id = $this->postJson('/escenarios-store', ['nombre' => 'Básico', 'descripcion' => 'Test', 'archivo' => $this->excel()])->assertCreated()->json('data.id');
        $available = $this->getJson("/escenarios-data/$id/resultados")->assertOk()->json('archivos.0.variables_disponibles');
        $this->assertContains('potencia_solar_w', $available);
        $this->assertContains('energia_almacenada_wh', $available);
        $this->assertNotContains('estado_carga_pct', $available);
    }

    public function test_workbooks_without_result_sheets_are_kept_as_documents(): void
    {
        $timesheet = $this->workbook(['Horas' => [['Nombre', 'Horas'], ['Ana', 8]]], 'planilla.xlsx');
        foreach ([$this->contextWorkbook(), $timesheet] as $index => $file) {
            $this->postJson('/escenarios-store', ['nombre' => 'Documento '.($index + 1), 'descripcion' => 'Test', 'archivo' => $file])
                ->assertCreated()
                ->assertJsonPath('data.contenidos.0.tipo', 'documento');
        }
        $this->assertDatabaseCount('resultados_simulacion', 0);
        $this->postJson('/escenarios-store', ['nombre' => 'Texto', 'descripcion' => 'Test', 'archivo' => UploadedFile::fake()->createWithContent('nota.txt', 'hello')])->assertUnprocessable();
        $this->assertDatabaseCount('esceanarios', 2);
    }

    public function test_invalid_result_workbooks_are_rejected_with_location_details(): void
    {
        $cases = [
            'falta la hoja «Cada10min»' => $this->excel(missing: true),
            'hoja «Minutos», fila 2, columna «Potencia solar (W)» (F2): «abc» no es un número' => $this->excel(tweak: function ($row, $title) {
                if ($title === 'Minutos') {
                    $row[5] = 'abc';
                }

                return $row;
            }),
            'Usa punto como separador decimal' => $this->excel(tweak: function ($row) {
                $row[1] = '11,5';

                return $row;
            }),
            'no puede ser negativo' => $this->excel(tweak: function ($row) {
                $row[5] = -10;

                return $row;
            }),
            'es un porcentaje y no puede superar 100' => $this->excel(extended: true, tweak: function ($row) {
                $row[13] = 140;

                return $row;
            }),
            'debe ser un número entero' => $this->excel(tweak: function ($row) {
                $row[11] = 2.5;

                return $row;
            }),
            'no es múltiplo del intervalo de 5 min' => $this->excel(tweak: function ($row, $title) {
                if ($title === 'Cada5min') {
                    $row[0] = 7;
                }

                return $row;
            }),
        ];

        foreach ($cases as $expected => $file) {
            $errors = $this->postJson('/escenarios-store', ['nombre' => 'Inválido', 'descripcion' => 'Test', 'archivo' => $file])
                ->assertUnprocessable()
                ->json('errors.archivo');
            $this->assertStringContainsString($expected, implode("\n", $errors), "Esperaba: $expected");
            $this->assertStringStartsWith('«'.SimulationResultsImport::OFFICIAL_FILENAME.'»', $errors[0]);
        }

        $this->assertDatabaseCount('esceanarios', 0);
        $this->assertDatabaseCount('escenario_contenidos', 0);
        $this->assertDatabaseCount('resultados_simulacion', 0);
        $this->assertFalse(File::isDirectory(storage_path('app/public/escenarios')) && File::allFiles(storage_path('app/public/escenarios')));
    }

    public function test_structure_problems_are_reported(): void
    {
        $headers = SimulationResultsImport::HEADERS;
        $row = [1, 11.6, 0, 24, 6, 0, 0, 500000, 0, 0, 0, 0, 0];
        $sheets = fn (array $minutes) => ['Minutos' => $minutes, 'Cada5min' => [$headers, [5, ...array_slice($row, 1)]], 'Cada10min' => [$headers, [10, ...array_slice($row, 1)]], 'Horas' => [$headers, $row]];

        $unknown = $this->workbook($sheets([[...$headers, 'Columna nueva'], [...$row, 4]]));
        $missingColumn = $this->workbook($sheets([array_slice($headers, 0, 12), array_slice($row, 0, 12)]));
        $duplicatedTime = $this->workbook($sheets([$headers, $row, $row]));
        $decreasing = $this->workbook($sheets([$headers, [1, 11.6, 0, 24, 6, 0, 0, 500000, 0, 5, 0, 0, 0], [2, 11.6, 0, 24, 6, 0, 0, 500000, 0, 4, 0, 0, 0]]));

        $this->postJson('/escenarios-store', ['nombre' => 'A', 'descripcion' => 'Test', 'archivo' => $unknown])->assertUnprocessable()
            ->assertJsonFragment(['«'.SimulationResultsImport::OFFICIAL_FILENAME.'»: hoja «Minutos»: la columna «Columna nueva» no pertenece al formato de resultados.']);
        $this->postJson('/escenarios-store', ['nombre' => 'B', 'descripcion' => 'Test', 'archivo' => $missingColumn])->assertUnprocessable()
            ->assertJsonFragment(['«'.SimulationResultsImport::OFFICIAL_FILENAME.'»: hoja «Minutos»: falta la columna «Lodos finos (paquetes de 10 kg)».']);
        $message = implode(' ', $this->postJson('/escenarios-store', ['nombre' => 'C', 'descripcion' => 'Test', 'archivo' => $duplicatedTime])->assertUnprocessable()->json('errors.archivo'));
        $this->assertStringContainsString('fila 3: el tiempo 1 min está repetido (ya aparece en la fila 2)', $message);
        $message = implode(' ', $this->postJson('/escenarios-store', ['nombre' => 'D', 'descripcion' => 'Test', 'archivo' => $decreasing])->assertUnprocessable()->json('errors.archivo'));
        $this->assertStringContainsString('columna «Agua desalinizada (m3)»: es un acumulado y disminuye de 5 (minuto 1) a 4 (minuto 2)', $message);
        $this->assertDatabaseCount('esceanarios', 0);
    }

    public function test_errors_of_every_uploaded_workbook_are_reported_together(): void
    {
        $bad = fn (string $name) => $this->excel(missing: true, name: $name);
        $this->postJson('/escenarios-store', [
            'nombre' => 'Carga múltiple',
            'descripcion' => 'Test',
            'archivos' => [$this->excel(), $bad('uno.xlsx'), $this->word(), $bad('dos.xlsx')],
        ])->assertUnprocessable()->assertJsonValidationErrors(['archivos.1', 'archivos.3'])->assertJsonMissingValidationErrors(['archivos.0', 'archivos.2']);
        $this->assertDatabaseCount('esceanarios', 0);
    }

    public function test_excel_and_word_files_can_be_uploaded_together_and_only_matching_data_is_imported(): void
    {
        $recognized = $this->excel();
        $reference = $this->contextWorkbook();
        $manual = $this->word();

        $response = $this->postJson('/escenarios-store', [
            'nombre' => 'Carga mixta',
            'descripcion' => 'Datos y anexos',
            'archivos' => [$recognized, $reference, $manual],
        ])->assertCreated();

        $response->assertJsonCount(3, 'data.contenidos');
        $scenarioId = $response->json('data.id');
        $manualId = collect($response->json('data.contenidos'))->firstWhere('nombre', 'manual.docx')['id'];
        $this->assertDatabaseCount('escenario_contenidos', 3);
        $this->assertDatabaseCount('resultados_simulacion', 4);
        $this->assertDatabaseHas('escenario_contenidos', ['tipo' => 'datos']);
        $this->assertDatabaseHas('escenario_contenidos', ['tipo' => 'documento', 'nombre' => 'manual.docx']);
        $this->assertDatabaseHas('escenario_contenidos', ['tipo' => 'documento', 'nombre' => 'Datos comunidad 1.xlsx']);
        $this->get("/escenarios/$scenarioId/contenidos/$manualId/download")->assertOk();
    }

    public function test_restoring_an_older_result_publishes_a_new_variation(): void
    {
        $original = $this->excel();
        $id = $this->postJson('/escenarios-store', ['nombre' => 'Variante solar', 'descripcion' => 'Test', 'archivo' => $original])->assertCreated()->json('data.id');
        $this->postJson("/escenarios/$id/contenidos", ['archivo' => $this->excel(power: 20)])->assertCreated();
        $this->postJson("/escenarios/$id/contenidos", ['archivo' => $original])->assertCreated();
        $this->assertDatabaseCount('data_versions', 3);
        $this->getJson("/escenarios-data/$id/resultados")->assertOk()
            ->assertJsonPath('version_actual', '1.2')
            ->assertJsonPath('archivos.0.version_datos', '1.2')
            ->assertJsonPath('archivos.0.es_actual', true)
            ->assertJsonPath('archivos.1.version_datos', '1.1')
            ->assertJsonPath('archivos.1.es_actual', false)
            ->assertJsonPath('archivos.2.version_datos', '1.0')
            ->assertJsonCount(3, 'versiones');
        foreach (['1.0' => 0, '1.1' => 20, '1.2' => 0] as $version => $expected) {
            $response = $this->getJson("/api/escenarios/$id/solar?version=$version")->assertOk();
            $this->assertEquals($expected, $response->json('muestreos.1.0.potencia_solar'));
        }
        $this->getJson("/api/escenarios/$id/versiones-datos?tipo=solar&version_actual=1.1")
            ->assertJsonPath('actualizacion_disponible', true)->assertJsonPath('version', '1.2');
    }

    public function test_new_simulation_routes_and_historic_solar_aliases_return_the_same_data(): void
    {
        $id = $this->postJson('/escenarios-store', ['nombre' => 'Alias', 'descripcion' => 'Test', 'archivo' => $this->excel(extended: true)])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('data_versions', ['escenario_id' => $id, 'tipo' => SimulationResultsImport::VERSION_TYPE, 'version' => '1.0']);

        $new = $this->getJson("/api/escenarios/$id/simulacion")->assertOk()->assertJsonPath('tipo', 'simulacion')->json();
        $old = $this->getJson("/api/escenarios/$id/solar")->assertOk()->assertJsonPath('tipo', 'solar')->json();
        $this->assertSame($new['muestreos'], $old['muestreos']);
        $this->assertSame($new['sha256'], $old['sha256']);

        $this->getJson("/api/escenarios/$id/versiones-datos?tipo=simulacion")->assertOk()
            ->assertJsonPath('tipo', 'simulacion')->assertJsonPath('version', '1.0')
            ->assertJsonPath('url_datos', "/api/escenarios/$id/simulacion?version=1.0");
        $this->getJson("/api/escenarios/$id/versiones-datos?tipo=solar&version_actual=1.0")->assertOk()
            ->assertJsonPath('estado', 'sin_cambios')
            ->assertJsonPath('url_datos', "/api/escenarios/$id/solar?version=1.0");
        $this->getJson("/api/escenarios/$id/versiones-datos?tipo=otro")->assertUnprocessable();

        $results = $this->getJson("/api/escenarios/$id/resultados-simulacion")->assertOk()->json('archivos');
        $this->assertSame($results, $this->getJson("/api/escenarios/$id/resultados-solares")->assertOk()->json('archivos'));
    }

    public function test_original_workbook_when_provided(): void
    {
        $path = getenv('SOLAR_SAMPLE_FILE');
        if (! $path || ! is_file($path)) {
            $this->markTestSkipped('Archivo original opcional no proporcionado.');
        }
        $file = UploadedFile::fake()->createWithContent(basename($path), file_get_contents($path));
        $id = $this->postJson('/escenarios-store', ['nombre' => 'Original', 'descripcion' => 'Validación aislada', 'archivo' => $file])->assertCreated()->json('data.id');
        $this->assertDatabaseCount('resultados_simulacion', 2370);
        $response = $this->getJson("/api/escenarios/$id/resultados-solares")->assertOk();
        $response->assertJsonCount(1800, 'archivos.0.muestreos.1')->assertJsonCount(360, 'archivos.0.muestreos.5')->assertJsonCount(180, 'archivos.0.muestreos.10')->assertJsonCount(30, 'archivos.0.muestreos.60');
        $this->getJson("/api/escenarios/$id/versiones-datos?tipo=solar")->assertOk()->assertJsonPath('estado', 'descarga_inicial')->assertJsonPath('version', '1.0');
        $this->getJson("/api/escenarios/$id/solar?version=1.0")->assertOk()->assertJsonCount(1800, 'muestreos.1')->assertJsonCount(360, 'muestreos.5')->assertJsonCount(180, 'muestreos.10');
        $this->getJson("/api/escenarios/$id/versiones-datos?tipo=solar&version_actual=1.0")->assertJsonPath('estado', 'sin_cambios');
    }
}
