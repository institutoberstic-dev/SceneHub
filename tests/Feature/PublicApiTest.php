<?php

namespace Tests\Feature;

use App\Models\Escenario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_average_upload_and_retired_routes(): void
    {
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $book->getActiveSheet()->fromArray([
            ['id_meeting', 'nivel_atencion_prom', 'emocion_ganadora_prom'],
            [10, 'ATENTO', 'disgust'],
        ]);
        $stream = fopen('php://memory', 'w+');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($stream);
        rewind($stream);
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('promedio.xlsx', stream_get_contents($stream));
        fclose($stream);
        $book->disconnectWorksheets();
        $this->postJson('/api/emociones/datos', ['archivo' => $file])->assertCreated()->assertJsonPath('webinars.0', 10);
        $this->getJson('/api/emociones/10')->assertOk()->assertJsonPath('summary.0.emocion_ganadora_prom', 'disgust');
        $this->postJson('/api/emociones/datos', [])->assertUnprocessable();
        foreach (['/api/users', '/api/roles', '/api/me', '/api/escenarios/1/emociones-promedio'] as $path) {
            $this->getJson($path)->assertNotFound();
        }
        $this->getJson('/users-data')->assertUnauthorized();
        $this->getJson('/roles-data')->assertUnauthorized();
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
