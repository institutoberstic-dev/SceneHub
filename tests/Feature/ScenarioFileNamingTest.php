<?php

namespace Tests\Feature;

use App\Models\Escenario;
use App\Models\User;
use App\Services\ScenarioFiles;
use App\Services\SimulationResultsImport;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Nombres canónicos: «resultados escenario {número del escenario}» para el libro de datos e
 * «informe escenario {número del informe}» para los Word, y el reconocimiento previo de archivos.
 */
class ScenarioFileNamingTest extends TestCase
{
    use RefreshDatabase;

    private const SHEETS = ['Minutos' => 1, 'Cada5min' => 5, 'Cada10min' => 10, 'Horas' => 60];

    private string $temporaryStorage;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporaryStorage = sys_get_temp_dir().'/naming-test-'.bin2hex(random_bytes(8));
        $this->app->useStoragePath($this->temporaryStorage);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->create();
        $this->owner->assignRole('cliente');
        $this->actingAs($this->owner);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryStorage);
        parent::tearDown();
    }

    private function results(string $name, float $power = 0, bool $missingSheet = false): UploadedFile
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);
        foreach (self::SHEETS as $title => $interval) {
            if ($missingSheet && $interval === 10) {
                continue;
            }
            $row = [$title === 'Horas' ? 1 : $interval, 11.6, 0, 24.1, 6.5, $power, -3, 500000, 3, 0, 0, 0, 0];
            $book->createSheet()->setTitle($title)->fromArray([SimulationResultsImport::HEADERS, $row], null, 'A1', true);
        }
        $path = tempnam(sys_get_temp_dir(), 'naming-');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
        $file = UploadedFile::fake()->createWithContent($name, file_get_contents($path));
        unlink($path);

        return $file;
    }

    private function sheet(string $name): UploadedFile
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->setTitle('Datos')->fromArray([['Datos comunidad'], ['población', 700]]);
        $path = tempnam(sys_get_temp_dir(), 'naming-');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
        $file = UploadedFile::fake()->createWithContent($name, file_get_contents($path));
        unlink($path);

        return $file;
    }

    private function word(string $name, string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function directory(int $id): string
    {
        return storage_path('app/public/escenarios/'.Escenario::findOrFail($id)->storage_directory);
    }

    private function create(array $files, array $extra = [])
    {
        return $this->postJson('/escenarios-store', ['nombre' => $extra['nombre'] ?? 'Escenario', 'descripcion' => 'Prueba', 'archivos' => $files] + $extra);
    }

    public function test_numbers_are_read_from_misspelled_names(): void
    {
        $reports = [
            'Informe_esc 1.docx' => 1,
            'informe escenario1.docx' => 1,
            'Informe esc 2.docx' => 2,
            'INFORME No. 3.docx' => 3,
            'Informe de escenario 4 (septiembre 2026).docx' => 4,
            'informe 07.doc' => 7,
            'Informe final.docx' => null,
            'Informe final (1).docx' => null,
            'Informe v2.docx' => null,
            'Scene_Hub_Informe_Tecnico_2026-09-16.docx' => null,
            'informe escenario 0.docx' => null,
        ];
        foreach ($reports as $name => $expected) {
            $this->assertSame($expected, ScenarioFiles::reportNumberFromName($name), $name);
        }

        $scenarios = [
            'resultados escenario 1 1.xlsx' => 1,
            'Resultados caso 1.xlsx' => 1,
            'Resultdos_esc2.xlsx' => 2,
            'resultados escenario 12.xlsx' => 12,
            'resultados.xlsx' => null,
        ];
        foreach ($scenarios as $name => $expected) {
            $this->assertSame($expected, ScenarioFiles::scenarioNumberFromName($name), $name);
        }
        // Nombres de escenario (no son nombres de archivo: «No. 2» no es una extensión).
        $this->assertSame(2, ScenarioFiles::scenarioNumberFromName('Escenario No. 2', false));
        $this->assertSame(3, ScenarioFiles::scenarioNumberFromName('Esc. 3 solar', false));
    }

    public function test_first_scenario_stores_misnamed_results_and_report_with_canonical_names(): void
    {
        $response = $this->create([$this->results('Resultdos escenaro uno.xlsx'), $this->word('Informe_esc 1.docx', 'v1'), $this->sheet('Datos comunidad.xlsx')], ['nombre' => 'Escenario 1'])
            ->assertCreated()
            ->assertJsonPath('data.numero', 1)
            ->assertJsonPath('archivos.0.nombre', 'resultados escenario 1.xlsx')
            ->assertJsonPath('archivos.0.nombre_original', 'Resultdos escenaro uno.xlsx')
            ->assertJsonPath('archivos.1.nombre', 'informe escenario 1.docx')
            ->assertJsonPath('archivos.2.nombre', 'Datos comunidad.xlsx');
        $this->assertStringContainsString('Se guardó «Resultdos escenaro uno.xlsx» como «resultados escenario 1.xlsx»', $response->json('message'));
        $this->assertStringContainsString('1 informe quedó disponible', $response->json('message'));

        $directory = $this->directory($response->json('data.id'));
        $this->assertFileExists($directory.'/1.0/resultados escenario 1.xlsx');
        $this->assertFileExists($directory.'/1.0/informe escenario 1.docx');
        $this->assertFileExists($directory.'/1.0/Datos comunidad.xlsx');
        $this->assertFileDoesNotExist($directory.'/1.0/Resultdos escenaro uno.xlsx');
        $this->assertDatabaseHas('escenario_contenidos', ['nombre' => 'resultados escenario 1.xlsx', 'nombre_original' => 'Resultdos escenaro uno.xlsx', 'tipo' => 'datos']);
        $this->assertDatabaseHas('escenario_contenidos', ['nombre' => 'informe escenario 1.docx', 'nombre_original' => 'Informe_esc 1.docx', 'tipo' => 'informe']);
        $this->assertDatabaseCount('resultados_simulacion', 4);
    }

    public function test_scenario_number_comes_from_the_form_the_results_name_or_the_next_free_one(): void
    {
        $this->create([$this->results('resultados escenario 3.xlsx')], ['nombre' => 'Tres'])->assertCreated()
            ->assertJsonPath('data.numero', 3)
            ->assertJsonPath('archivos.0.nombre', 'resultados escenario 3.xlsx');
        // El 3 ya está ocupado: se usa el menor libre y se avisa que el nombre indicaba otro escenario.
        $this->create([$this->results('resultados escenario 3.xlsx', power: 5)], ['nombre' => 'Otro'])->assertCreated()
            ->assertJsonPath('data.numero', 1)
            ->assertJsonPath('archivos.0.nombre', 'resultados escenario 1.xlsx')
            ->assertJsonPath('archivos.0.advertencias.0', 'El nombre indica el escenario 3; se guardará como «resultados escenario 1.xlsx».');
        $this->create([$this->results('datos.xlsx', power: 6)], ['nombre' => 'Siete', 'numero' => 7])->assertCreated()
            ->assertJsonPath('data.numero', 7)
            ->assertJsonPath('archivos.0.nombre', 'resultados escenario 7.xlsx');
        $this->create([$this->word('informe.docx', 'x')], ['nombre' => 'Repetido', 'numero' => 7])->assertUnprocessable()
            ->assertJsonValidationErrors(['numero' => 'Ya existe un escenario con el número 7.']);
        $this->create([$this->word('informe.docx', 'x')], ['nombre' => 'Sin datos'])->assertCreated()->assertJsonPath('data.numero', 2);
    }

    public function test_reports_keep_their_number_replace_on_reupload_and_count_new_ones(): void
    {
        $id = $this->create([$this->results('resultados escenario 1.xlsx'), $this->word('Informe_esc 1.docx', 'v1')])->assertCreated()->json('data.id');
        $directory = $this->directory($id);

        // Mismo número con contenido nuevo ⇒ reemplaza en una nueva versión.
        $this->postJson("/escenarios/$id/contenidos", ['archivos' => [$this->word('informe escenario1.docx', 'v2')]])->assertCreated()
            ->assertJsonPath('archivos.0.nombre', 'informe escenario 1.docx')
            ->assertJsonPath('archivos.0.accion', 'reemplaza')
            ->assertJsonPath('data.version', '1.1');
        $this->assertSame('v1', File::get($directory.'/1.0/informe escenario 1.docx'));
        $this->assertSame('v2', File::get($directory.'/1.1/informe escenario 1.docx'));
        $this->assertFileExists($directory.'/1.1/resultados escenario 1.xlsx');

        // Número nuevo ⇒ informe nuevo en la versión vigente; sin número ⇒ siguiente consecutivo.
        $this->postJson("/escenarios/$id/contenidos", ['archivos' => [$this->word('Informe esc 2.docx', 'segundo'), $this->word('Informe final.docx', 'tercero')]])->assertCreated()
            ->assertJsonPath('archivos.0.nombre', 'informe escenario 2.docx')
            ->assertJsonPath('archivos.0.accion', 'nuevo')
            ->assertJsonPath('archivos.1.nombre', 'informe escenario 3.docx');
        $this->assertFileExists($directory.'/1.1/informe escenario 2.docx');
        $this->assertFileExists($directory.'/1.1/informe escenario 3.docx');

        // Sin número pero con el mismo contenido de un informe existente ⇒ no se duplica.
        $this->postJson("/escenarios/$id/contenidos", ['archivos' => [$this->word('Informe copia.docx', 'segundo')]])->assertOk()
            ->assertJsonPath('archivos.0.nombre', 'informe escenario 2.docx')
            ->assertJsonPath('archivos.0.accion', 'sin_cambios');
        // Sin número y con el nombre original de un informe anterior ⇒ lo reemplaza.
        $this->postJson("/escenarios/$id/contenidos", ['archivos' => [$this->word('Informe final.docx', 'tercero corregido')]])->assertCreated()
            ->assertJsonPath('archivos.0.nombre', 'informe escenario 3.docx')
            ->assertJsonPath('archivos.0.accion', 'reemplaza');
        $this->assertDatabaseCount('escenario_contenidos', 6);
        $this->assertSame(1.2, (float) Escenario::find($id)->versiones);
    }

    public function test_reupload_through_the_edit_form_uses_the_canonical_name(): void
    {
        $id = $this->create([$this->results('resultados escenario 1.xlsx')], ['nombre' => 'Edición'])->assertCreated()->json('data.id');
        $this->putJson("/escenarios/$id", [
            'nombre' => 'Edición',
            'descripcion' => 'Nueva corrida',
            'estado' => 'Activo',
            'archivo' => $this->results('RESULTADOS FINAL.xlsx', power: 9),
        ])->assertOk()
            ->assertJsonPath('archivos.0.nombre', 'resultados escenario 1.xlsx')
            ->assertJsonPath('archivos.0.accion', 'reemplaza')
            ->assertJsonPath('data.versiones', 1.1);
        $this->assertFileExists($this->directory($id).'/1.1/resultados escenario 1.xlsx');
        $this->assertFileDoesNotExist($this->directory($id).'/1.1/RESULTADOS FINAL.xlsx');
    }

    public function test_conflicting_uploads_are_rejected_before_storing_anything(): void
    {
        $this->create([$this->results('a.xlsx'), $this->results('b.xlsx', power: 3)])->assertUnprocessable()
            ->assertJsonValidationErrors(['archivos.1'])
            ->assertJsonFragment(['«b.xlsx»: ya hay otro libro de resultados en esta carga («a.xlsx»). Cada escenario tiene un solo archivo de datos («resultados escenario 1.xlsx»); deja solo uno.']);
        $this->create([$this->word('informe 1.docx', 'a'), $this->word('Informe_esc1.docx', 'b')])->assertUnprocessable()
            ->assertJsonValidationErrors(['archivos.1']);
        $this->create([$this->sheet('resultados escenario 1.xlsx')])->assertUnprocessable()
            ->assertJsonValidationErrors(['archivos.0']);
        $this->assertDatabaseCount('esceanarios', 0);

        // Mismo número con otra extensión en la misma carga.
        $this->create([$this->results('a.xlsx'), $this->results('b.xls', power: 2)])->assertUnprocessable()->assertJsonValidationErrors(['archivos.1']);
        $this->create([$this->word('informe 2.doc', 'a'), $this->word('informe 2.docx', 'b')])->assertUnprocessable()->assertJsonValidationErrors(['archivos.1']);
        $this->assertDatabaseCount('esceanarios', 0);

        $id = $this->create([$this->word('informe 1.docx', 'a')])->assertCreated()->json('data.id');
        // El informe 1 en otro formato lo reemplaza: la nueva versión ya no incluye el .docx.
        $this->postJson("/escenarios/$id/contenidos", ['archivos' => [$this->word('informe 1.pdf', 'b')]])->assertCreated()
            ->assertJsonPath('archivos.0.nombre', 'informe escenario 1.pdf')
            ->assertJsonPath('archivos.0.accion', 'reemplaza');
        $this->assertSame(['informe escenario 1.pdf'], array_map('basename', File::files($this->directory($id).'/1.1')));
    }

    public function test_analysis_recognizes_the_scenario_file_without_storing(): void
    {
        $response = $this->post('/escenarios-analizar', [
            'archivos' => [
                $this->word('Informe esc 2.docx', 'b'),
                $this->results('Resultados escenario 4.xlsx'),
                $this->results('roto.xlsx', missingSheet: true),
                UploadedFile::fake()->createWithContent('notas.txt', 'hola'),
                $this->sheet('Datos comunidad.xlsx'),
            ],
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('escenario.numero_sugerido', 4)
            ->assertJsonPath('escenario.nombre_sugerido', 'Escenario 4')
            ->assertJsonPath('archivo_escenario', 1)
            ->assertJsonPath('archivos.0.tipo', 'informe')
            ->assertJsonPath('archivos.0.numero_informe', 2)
            ->assertJsonPath('archivos.0.origen_numero', 'nombre')
            ->assertJsonPath('archivos.1.tipo', 'resultados')
            ->assertJsonPath('archivos.1.valido', true)
            ->assertJsonPath('archivos.1.resultados.formato', 'basico')
            ->assertJsonPath('archivos.1.resultados.registros.Minutos', 1)
            ->assertJsonPath('archivos.1.numero_escenario_en_nombre', 4)
            ->assertJsonPath('archivos.2.tipo', 'resultados')
            ->assertJsonPath('archivos.2.valido', false)
            ->assertJsonPath('archivos.3.valido', false)
            ->assertJsonPath('archivos.4.tipo', 'documento')
            ->assertJsonPath('archivos.4.valido', true);
        $this->assertStringContainsString('falta la hoja «Cada10min»', implode(' ', $response->json('archivos.2.errores')));
        $this->assertStringContainsString('«notas.txt»', $response->json('archivos.3.errores.0'));
        $this->assertSame(hash('sha256', 'b'), $response->json('archivos.0.sha256'));
        $this->assertDatabaseCount('esceanarios', 0);
        $this->assertDirectoryDoesNotExist(storage_path('app/public/escenarios'));
    }

    public function test_analysis_of_an_existing_scenario_lists_its_files_and_requires_the_owner(): void
    {
        $id = $this->create([$this->results('resultados escenario 1.xlsx'), $this->word('Informe base.docx', 'uno')], ['nombre' => 'Escenario 1'])->assertCreated()->json('data.id');

        $response = $this->postJson('/escenarios-analizar', ['escenario_id' => $id, 'archivos' => [$this->word('Informe base.docx', 'uno corregido')]])->assertOk()
            ->assertJsonPath('escenario.numero', 1)
            ->assertJsonPath('archivos.0.numero_informe', 1)
            ->assertJsonPath('archivos.0.origen_numero', 'nombre_original');
        $existing = collect($response->json('existentes'))->keyBy('nombre');
        $this->assertSame(['informe escenario 1.docx', 'resultados escenario 1.xlsx'], $existing->keys()->sort()->values()->all());
        $this->assertSame(hash('sha256', 'uno'), $existing['informe escenario 1.docx']['sha256']);
        $this->assertSame(1, $existing['informe escenario 1.docx']['numero']);

        $other = User::factory()->create();
        $other->assignRole('cliente');
        $this->actingAs($other)->postJson('/escenarios-analizar', ['escenario_id' => $id, 'archivos' => [$this->word('x.docx', 'x')]])->assertForbidden();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin)->postJson('/escenarios-analizar', ['archivos' => [$this->word('x.docx', 'x')]])->assertForbidden();
    }

    /** Simula un archivo guardado antes de los nombres canónicos. */
    private function renameStored(int $id, string $from, string $to): void
    {
        $directory = $this->directory($id).'/1.0';
        rename("$directory/$from", "$directory/$to");
        $content = DB::table('escenario_contenidos')->where('escenario_id', $id)->where('nombre', $from)->first();
        DB::table('escenario_contenidos')->where('id', $content->id)->update(['nombre' => $to, 'ruta' => str_replace($from, $to, $content->ruta)]);
    }

    public function test_a_results_workbook_saved_with_an_old_name_is_replaced_in_the_next_version(): void
    {
        $id = $this->create([$this->results('x.xlsx'), $this->word('informe 1.docx', 'uno')], ['nombre' => 'Legado'])->assertCreated()->json('data.id');
        $this->renameStored($id, 'resultados escenario 1.xlsx', 'Resultados caso 1.xlsx');
        $directory = $this->directory($id);

        $analysis = $this->postJson('/escenarios-analizar', ['escenario_id' => $id, 'archivos' => [$this->results('nuevo.xlsx', power: 4)]])->assertOk();
        $legacy = collect($analysis->json('existentes'))->firstWhere('nombre', 'Resultados caso 1.xlsx');
        $this->assertTrue($legacy['resultados_anterior']);

        $this->postJson("/escenarios/$id/contenidos", ['archivos' => [$this->results('nuevo.xlsx', power: 4)]])->assertCreated()
            ->assertJsonPath('archivos.0.nombre', 'resultados escenario 1.xlsx')
            ->assertJsonPath('archivos.0.accion', 'reemplaza')
            ->assertJsonPath('data.version', '1.1');
        $this->assertFileExists($directory.'/1.0/Resultados caso 1.xlsx');
        $this->assertFileExists($directory.'/1.1/resultados escenario 1.xlsx');
        $this->assertFileDoesNotExist($directory.'/1.1/Resultados caso 1.xlsx');
        $this->assertFileExists($directory.'/1.1/informe escenario 1.docx');
    }

    public function test_names_that_only_differ_in_case_are_the_same_file(): void
    {
        $id = $this->create([$this->word('Informe 1.docx', 'uno')], ['nombre' => 'Mayúsculas'])->assertCreated()->json('data.id');
        $this->renameStored($id, 'informe escenario 1.docx', 'Informe Escenario 1.docx');
        $directory = $this->directory($id);

        $this->postJson("/escenarios/$id/contenidos", ['archivos' => [$this->word('informe 1.docx', 'uno')]])->assertOk()
            ->assertJsonPath('archivos.0.accion', 'sin_cambios');
        $this->postJson("/escenarios/$id/contenidos", ['archivos' => [$this->word('informe 1.docx', 'uno corregido')]])->assertCreated()
            ->assertJsonPath('archivos.0.accion', 'reemplaza')
            ->assertJsonPath('data.version', '1.1');
        $this->assertSame(['informe escenario 1.docx'], array_map('basename', File::files($directory.'/1.1')));
    }

    public function test_content_lists_only_the_files_of_the_current_version(): void
    {
        $id = $this->create([$this->results('x.xlsx'), $this->word('informe 1.docx', 'uno'), $this->sheet('Datos comunidad.xlsx')], ['nombre' => 'Vigente'])->assertCreated()->json('data.id');
        $this->renameStored($id, 'resultados escenario 1.xlsx', 'Resultados caso 1.xlsx');
        // Reemplaza el libro (nombre anterior) y el informe 1, y agrega el informe 2 ⇒ versiones 1.1 y 1.2.
        $this->postJson("/escenarios/$id/contenidos", ['archivos' => [$this->results('nuevo.xlsx', power: 4), $this->word('informe 1.docx', 'uno bis'), $this->word('informe 2.docx', 'dos')]])->assertCreated();

        $response = $this->getJson("/escenarios-data/$id")->assertOk()->assertJsonPath('version_vigente', '1.2');
        $current = collect($response->json('contenido_actual'));
        $this->assertSame(['Datos comunidad.xlsx', 'informe escenario 1.docx', 'informe escenario 2.docx', 'resultados escenario 1.xlsx'], $current->pluck('nombre')->all());
        // Heredado de la versión 1.0 y reemplazado en 1.2.
        $this->assertSame('1.0', $current->firstWhere('nombre', 'Datos comunidad.xlsx')['version']);
        $this->assertSame('1.2', $current->firstWhere('nombre', 'informe escenario 1.docx')['version']);
        $this->assertCount(6, $response->json('contenidos'));
        $this->assertSame(['1.2', '1.1', '1.0'], collect($response->json('versiones_contenido'))->pluck('version')->all());
        $this->assertContains('Resultados caso 1.xlsx', collect($response->json('versiones_contenido.2.archivos'))->pluck('nombre')->all());

        $card = collect($this->getJson('/escenarios-data')->assertOk()->json())->firstWhere('id', $id);
        $this->assertSame(4, $card['archivos_vigentes']);
    }

    public function test_reports_are_recognized_by_name_in_any_format(): void
    {
        $response = $this->create([
            $this->word('Reporte técnico esc 2.pdf', '%PDF-1.4 dos'),
            $this->word('Informe final.docx', 'final'),
            $this->word('Anexo planos.docx', 'anexo'),
            $this->word('manual.pdf', '%PDF-1.4 manual'),
        ], ['nombre' => 'Formatos'])->assertCreated();
        $files = collect($response->json('archivos'))->keyBy('nombre_original');
        $this->assertSame(['informe escenario 2.pdf', 'informe', 2], [$files['Reporte técnico esc 2.pdf']['nombre'], $files['Reporte técnico esc 2.pdf']['tipo'], $files['Reporte técnico esc 2.pdf']['numero']]);
        $this->assertSame('informe escenario 3.docx', $files['Informe final.docx']['nombre']);
        $this->assertSame(['Anexo planos.docx', 'documento'], [$files['Anexo planos.docx']['nombre'], $files['Anexo planos.docx']['tipo']]);
        $this->assertSame(['manual.pdf', 'documento'], [$files['manual.pdf']['nombre'], $files['manual.pdf']['tipo']]);
    }

    public function test_an_existing_report_is_only_updated_by_a_newer_file(): void
    {
        $saved = strtotime('2026-10-01 10:00:00') * 1000;
        $id = $this->postJson('/escenarios-store', ['nombre' => 'Fechas', 'descripcion' => 'Prueba', 'archivos' => [$this->word('Informe 1.pdf', 'v1')], 'fechas' => [$saved]])
            ->assertCreated()->json('data.id');
        $this->assertDatabaseHas('escenario_contenidos', ['nombre' => 'informe escenario 1.pdf', 'fecha_archivo' => '2026-10-01 10:00:00']);

        // Más antiguo (o igual) que el guardado ⇒ no se carga; el resto de la carga sí.
        $this->postJson("/escenarios/$id/contenidos", [
            'archivos' => [$this->word('Informe 1.pdf', 'v0 vieja'), $this->word('Informe 2.pdf', 'dos')],
            'fechas' => [$saved - 86400000, $saved],
        ])->assertCreated()
            ->assertJsonPath('archivos.0.accion', 'omitido')
            ->assertJsonPath('archivos.1.accion', 'nuevo');
        $this->assertSame('v1', File::get($this->directory($id).'/1.0/informe escenario 1.pdf'));
        $this->postJson("/escenarios/$id/contenidos", ['archivos' => [$this->word('Informe 1.pdf', 'misma fecha')], 'fechas' => [$saved]])->assertOk()
            ->assertJsonPath('archivos.0.accion', 'omitido');
        $this->assertStringContainsString('solo se actualiza con un archivo más reciente', $this->postJson("/escenarios/$id/contenidos", ['archivos' => [$this->word('Informe 1.pdf', 'otra')], 'fechas' => [$saved]])->json('message'));

        // Más reciente ⇒ actualiza en una nueva versión.
        $this->postJson("/escenarios/$id/contenidos", ['archivos' => [$this->word('Informe 1.pdf', 'v2')], 'fechas' => [$saved + 3600000]])->assertCreated()
            ->assertJsonPath('archivos.0.accion', 'reemplaza')
            ->assertJsonPath('data.version', '1.1');
        $this->assertSame('v2', File::get($this->directory($id).'/1.1/informe escenario 1.pdf'));

        $existing = collect($this->postJson('/escenarios-analizar', ['escenario_id' => $id, 'archivos' => [$this->word('x.pdf', 'x')]])->json('existentes'))->keyBy('nombre');
        $this->assertSame(intdiv($saved, 1000) + 3600, $existing['informe escenario 1.pdf']['fecha_archivo']);
    }

    public function test_migration_numbers_existing_scenarios_from_their_names(): void
    {
        DB::table('esceanarios')->insert([
            ['nombre' => 'Prueba sin número', 'descripcion' => '-', 'estado' => 'Activo', 'versiones' => 1, 'numero' => null],
            ['nombre' => 'Escenario 1', 'descripcion' => '-', 'estado' => 'Activo', 'versiones' => 1, 'numero' => null],
            ['nombre' => 'Escenario 3 hidrógeno', 'descripcion' => '-', 'estado' => 'Activo', 'versiones' => 1, 'numero' => null],
            ['nombre' => 'Copia escenario 1', 'descripcion' => '-', 'estado' => 'Activo', 'versiones' => 1, 'numero' => null],
            ['nombre' => 'Escenario_5', 'descripcion' => '-', 'estado' => 'Activo', 'versiones' => 1, 'numero' => null],
            ['nombre' => 'Escenario Nº 6', 'descripcion' => '-', 'estado' => 'Activo', 'versiones' => 1, 'numero' => null],
        ]);
        $migration = require database_path('migrations/2026_10_08_100000_add_numero_to_esceanarios_and_original_name_to_contents.php');
        (fn () => $this->assignNumbers())->call($migration);

        $this->assertSame(
            ['Prueba sin número' => 2, 'Escenario 1' => 1, 'Escenario 3 hidrógeno' => 3, 'Copia escenario 1' => 4, 'Escenario_5' => 5, 'Escenario Nº 6' => 6],
            DB::table('esceanarios')->orderBy('id')->pluck('numero', 'nombre')->map(fn ($n) => (int) $n)->all()
        );
    }
}
