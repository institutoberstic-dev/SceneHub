<?php

namespace Tests\Feature;

use App\Models\Escenario;
use App\Models\User;
use App\Services\ScenarioEmotionImport;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_average_upload_feeds_the_public_read_api(): void
    {
        $this->authenticateEmotionManager();
        Http::fake(['bersticlive.org/api/meetings' => Http::response(['meetings' => [
            ['id' => 10, 'title' => 'Webinar de hidrógeno', 'topic' => 'Energía sostenible'],
        ]])]);
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([
            ['id_meeting', 'nivel_atencion_prom', 'emocion_ganadora_prom'],
            [10, 'ATENTO', 'disgust'],
        ]);
        $stream = fopen('php://memory', 'w+');
        (new Xlsx($book))->save($stream);
        rewind($stream);
        $file = UploadedFile::fake()->createWithContent('promedio.xlsx', stream_get_contents($stream));
        fclose($stream);
        $book->disconnectWorksheets();
        $this->postJson('/emociones-data', ['archivo' => $file])->assertCreated()->assertJsonPath('webinars.0', 10);
        $this->getJson('/api/emociones/10')->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('webinar.titulo', 'Webinar de hidrógeno')
            ->assertJsonPath('webinar.tema', 'Energía sostenible')
            ->assertJsonPath('data_promedio.0.emocion_ganadora_prom', 'disgust')
            ->assertJsonCount(0, 'data_completa')
            ->assertJsonMissingPath('data_promedio.0.id_meeting');
        $this->getJson('/api/emociones')->assertOk()
            ->assertJsonPath('total_webinars', 1)
            ->assertJsonPath('webinars.0.webinar.titulo', 'Webinar de hidrógeno')
            ->assertJsonPath('webinars.0.data_promedio.0.nivel_atencion_prom', 'ATENTO');
        $this->postJson('/emociones-data', [])->assertUnprocessable();
        auth()->logout();
        $this->postJson('/api/emociones/datos', [])->assertNotFound();
        foreach (['/api/users', '/api/roles', '/api/me', '/api/escenarios/1/emociones-promedio'] as $path) {
            $this->getJson($path)->assertNotFound();
        }
        $this->getJson('/users-data')->assertUnauthorized();
        $this->getJson('/roles-data')->assertUnauthorized();
    }

    public function test_public_emotion_api_correlates_webinar_name_and_returns_complete_and_average_data(): void
    {
        $this->authenticateEmotionManager();
        Http::fake(['bersticlive.org/api/meetings' => Http::response(['data' => [
            ['meeting_id' => 77, 'name' => 'Webinar técnico Airsigla'],
        ]])]);

        $average = $this->emotionWorkbook([
            ['id_meeting', 'nivel_atencion_prom', 'emocion_ganadora_prom'],
            [77, 'ATENTO', 'happy'],
        ], 'promedio.xlsx');
        $headers = ['id_persona', 'id_meeting', 'archivo', 'nivel_atencion', ...ScenarioEmotionImport::METRICS, 'emocion_ganadora', 'fecha', 'tiempo', 'validez', 'estatus_calidad_DAMA'];
        $detail = $this->emotionWorkbook([
            $headers,
            [41, 77, 'frame-001.jpg', 'ATENTO', ...array_fill(0, count(ScenarioEmotionImport::METRICS), 0.125), 'happy', '2026-09-16', '10:03:05', 'VALIDO', 'VERIFICADO'],
        ], 'completo.xlsx');

        $this->postJson('/emociones-data', ['archivo' => $average])->assertCreated();
        $this->postJson('/emociones-data', ['archivo' => $detail])->assertCreated();

        $this->getJson('/api/emociones/77')->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('webinar.titulo', 'Webinar técnico Airsigla')
            ->assertJsonPath('webinar.tema', null)
            ->assertJsonPath('data_promedio.0.emocion_ganadora_prom', 'happy')
            ->assertJsonPath('data_completa.0.prob_happy', 0.125)
            ->assertJsonPath('data_completa.0.validez', true)
            ->assertJsonMissingPath('id_meeting')
            ->assertJsonMissingPath('webinar.id')
            ->assertJsonMissingPath('data_promedio.0.id')
            ->assertJsonMissingPath('data_promedio.0.id_meeting')
            ->assertJsonMissingPath('data_completa.0.id')
            ->assertJsonMissingPath('data_completa.0.id_persona')
            ->assertJsonMissingPath('data_completa.0.id_meeting')
            ->assertJsonPath('totales.registros', 1)
            ->assertJsonPath('totales.promedios', 1)
            ->assertJsonPath('totales.personas', 1)
            ->assertJsonPath('archivos_origen.promedio.0', 'promedio.xlsx')
            ->assertJsonPath('archivos_origen.datos_completos.0', 'completo.xlsx');

        $this->getJson('/api/emociones')->assertOk()
            ->assertJsonCount(1, 'webinars')
            ->assertJsonCount(1, 'webinars.0.data_promedio')
            ->assertJsonCount(1, 'webinars.0.data_completa');
    }

    private function emotionWorkbook(array $rows, string $name): UploadedFile
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray($rows, null, 'A1', true);
        $stream = fopen('php://memory', 'w+');
        (new Xlsx($book))->save($stream);
        rewind($stream);
        $file = UploadedFile::fake()->createWithContent($name, stream_get_contents($stream));
        fclose($stream);
        $book->disconnectWorksheets();

        return $file;
    }

    private function authenticateEmotionManager(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('gestor_emociones');
        $this->actingAs($user);
    }

    public function test_api_can_be_read_but_cannot_update_scenarios_without_a_session(): void
    {
        $temp = sys_get_temp_dir().'/public-api-'.bin2hex(random_bytes(8));
        $this->app->useStoragePath($temp);
        Http::fake(['bersticlive.org/api/meetings' => Http::response(['meetings' => []])]);
        $scenario = Escenario::create([
            'nombre' => 'API publica', 'descripcion' => 'Prueba',
            'owner_id' => User::factory()->create()->id, 'estado' => 'Activo', 'versiones' => 0,
        ]);

        try {
            foreach (['/api/emociones', '/api/emociones/10', '/api/escenarios'] as $path) {
                $this->getJson($path)->assertOk();
            }
            $this->getJson('/api/escenarios')->assertJsonPath('0.id', $scenario->id);
            foreach (['', '/solar', '/resultados-solares', '/versiones-datos?tipo=solar'] as $suffix) {
                $this->getJson('/api/escenarios/'.$scenario->id.$suffix)->assertOk();
            }
            $this->options('/api/escenarios', [], [
                'Origin' => 'https://example.org', 'Access-Control-Request-Method' => 'GET',
            ])->assertSuccessful()->assertHeader('Access-Control-Allow-Origin', '*');
        } finally {
            File::deleteDirectory($temp);
        }
    }
}
