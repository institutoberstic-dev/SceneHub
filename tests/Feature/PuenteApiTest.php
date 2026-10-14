<?php

namespace Tests\Feature;

use App\Http\Controllers\DataVersionsController;
use App\Models\Escenario;
use App\Models\EscenarioContenido;
use App\Models\PaqueteTerritorial;
use App\Models\PuenteToken;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PuenteApiTest extends TestCase
{
    use RefreshDatabase;

    private const RUTAS = [
        '/api/puente/estado',
        '/api/puente/catalogo',
        '/api/puente/escenarios',
    ];

    private string $carpeta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->carpeta = 'public/escenarios/9001-prueba-puente/1.0';
        File::ensureDirectoryExists(storage_path('app/'.$this->carpeta));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/public/escenarios/9001-prueba-puente'));
        parent::tearDown();
    }

    private function token(): string
    {
        return PuenteToken::emitir('PC de prueba')[1];
    }

    private function conToken(string $token): self
    {
        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    /** Escenario con una versión de datos cuyo archivo existe en disco. */
    private function escenarioConVersion(): array
    {
        $duenio = User::factory()->create(['email' => 'persona.real@example.invalid']);
        $escenario = Escenario::create(['numero' => 1, 'nombre' => 'Escenario 1', 'descripcion' => 'Prueba', 'owner_id' => $duenio->id, 'versiones' => 1.0, 'storage_directory' => '9001-prueba-puente']);
        $escenario->users()->attach($duenio->id, ['access_level' => 'owner', 'invited_by' => $duenio->id]);
        $ruta = $this->carpeta.'/resultados escenario 1.xlsx';
        File::put(storage_path('app/'.$ruta), 'contenido del libro de resultados');
        $contenido = EscenarioContenido::create(['escenario_id' => $escenario->id, 'uploaded_by' => $duenio->id, 'nombre' => 'resultados escenario 1.xlsx', 'ruta' => $ruta, 'tipo' => 'datos', 'tamano' => 10, 'version' => 1.0]);
        DB::table('data_versions')->insert(['escenario_id' => $escenario->id, 'archivo_id' => $contenido->id, 'tipo' => 'simulacion', 'revision' => 10, 'version' => '1.0', 'sha256' => str_repeat('b', 64), 'created_at' => now(), 'updated_at' => now()]);

        return [$escenario, storage_path('app/'.$ruta)];
    }

    public function test_sin_token_o_con_token_invalido_responde_401(): void
    {
        foreach (self::RUTAS as $ruta) {
            $this->getJson($ruta)->assertUnauthorized();
            $this->withHeader('Authorization', 'Bearer puente_inventado')->getJson($ruta)->assertUnauthorized();
        }
    }

    public function test_token_revocado_o_vencido_deja_de_funcionar_y_solo_admite_lectura(): void
    {
        [$modelo, $token] = PuenteToken::emitir('PC');
        $this->conToken($token)->getJson('/api/puente/estado')->assertOk()->assertJsonPath('version_api', '1');
        $this->conToken($token)->postJson('/api/puente/estado')->assertStatus(405);
        $this->assertNotNull($modelo->fresh()->ultimo_uso_en);

        $modelo->update(['revocado_en' => now()]);
        $this->conToken($token)->getJson('/api/puente/estado')->assertUnauthorized();

        [$vencido, $otro] = PuenteToken::emitir('PC vencido', 1);
        $vencido->update(['expira_en' => now()->subMinute()]);
        $this->conToken($otro)->getJson('/api/puente/estado')->assertUnauthorized();
    }

    public function test_el_token_en_claro_no_se_guarda(): void
    {
        [$modelo, $token] = PuenteToken::emitir('PC');
        $this->assertSame(hash('sha256', $token), $modelo->token_hash);
        $this->assertDatabaseMissing('puente_tokens', ['token_hash' => $token]);
        $this->assertArrayNotHasKey('token_hash', $modelo->toArray());
    }

    public function test_comandos_crear_listar_y_revocar(): void
    {
        $this->assertSame(0, Artisan::call('puente:token-crear', ['nombre' => 'PC del proyecto', '--dias' => 30]));
        $salida = Artisan::output();
        $this->assertMatchesRegularExpression('/puente_[a-f0-9]{64}/', $salida);
        $token = PuenteToken::firstOrFail();
        $this->assertNotNull($token->expira_en);
        $this->assertSame(0, Artisan::call('puente:token-listar'));
        $this->assertStringNotContainsString($token->token_hash, Artisan::output());
        $this->assertSame(0, Artisan::call('puente:token-revocar', ['id' => $token->id]));
        $this->assertNotNull($token->fresh()->revocado_en);
    }

    public function test_escenarios_con_lista_blanca_y_huella_del_archivo(): void
    {
        [$escenario, $ruta] = $this->escenarioConVersion();
        $respuesta = $this->conToken($this->token())->getJson('/api/puente/escenarios')->assertOk();

        $respuesta->assertJsonPath('escenarios.0.id', $escenario->id)
            ->assertJsonPath('escenarios.0.versiones_datos.0.version', '1.0')
            ->assertJsonPath('escenarios.0.versiones_datos.0.sha256', str_repeat('b', 64))
            ->assertJsonPath('escenarios.0.versiones_datos.0.archivo.sha256_archivo', hash_file('sha256', $ruta))
            ->assertJsonPath('escenarios.0.versiones_datos.0.archivo.url_descarga', "/api/puente/escenarios/{$escenario->id}/versiones/1.0/excel");
        $texto = $respuesta->getContent();
        foreach (['persona.real@example.invalid', '"owner"', '"users"', '"ruta"', 'storage_directory', 'public/escenarios'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $texto);
        }
        $this->assertSame(['id', 'numero', 'nombre', 'descripcion', 'tecnologias', 'versiones_datos'], array_keys($respuesta->json('escenarios.0')));
    }

    public function test_descarga_del_excel_original_por_version(): void
    {
        [$escenario, $ruta] = $this->escenarioConVersion();
        $token = $this->token();
        $descarga = $this->conToken($token)->get("/api/puente/escenarios/{$escenario->id}/versiones/1.0/excel")->assertOk();
        $this->assertSame(hash_file('sha256', $ruta), $descarga->headers->get('X-Content-SHA256'));
        $this->conToken($token)->get("/api/puente/escenarios/{$escenario->id}/versiones/9.9/excel")->assertNotFound();
        $this->flushHeaders()->get("/api/puente/escenarios/{$escenario->id}/versiones/1.0/excel")->assertUnauthorized();
    }

    public function test_catalogo_de_variables_alineado_con_las_unidades_de_unity(): void
    {
        $variables = collect($this->conToken($this->token())->getJson('/api/puente/catalogo')->assertOk()->json('variables'));
        $this->assertCount(17, $variables);
        $unidades = $variables->pluck('unidad', 'clave')->all();
        $esperadas = DataVersionsController::UNITS;
        ksort($unidades);
        ksort($esperadas);
        $this->assertSame($esperadas, $unidades);
    }

    public function test_territorio_solo_expone_versiones_publicadas(): void
    {
        Storage::fake('local');
        $this->seed(RolesAndPermissionsSeeder::class);
        $usuario = User::factory()->create();
        $usuario->assignRole('usuario');
        $usuario->givePermissionTo(['territorio.ver', 'territorio.subir', 'territorio.publicar']);
        $this->actingAs($usuario);
        $publicado = $this->postJson('/territorio-paquetes', ['version' => 'v01'])->json('data.id');
        $this->postJson("/territorio-paquetes/{$publicado}/archivos", ['tipo' => 'malla_gpkg', 'archivo' => TerritorioModuleTest::gpkg()])->assertCreated();
        $this->postJson("/territorio-paquetes/{$publicado}/archivos", ['tipo' => 'sitio_json', 'archivo' => TerritorioModuleTest::archivoJson('sitio.json', ['valores' => []])])->assertCreated();
        $this->postJson("/territorio-paquetes/{$publicado}/archivos", ['tipo' => 'escena_glb', 'archivo' => TerritorioModuleTest::glb()])->assertCreated();
        $this->postJson("/territorio-paquetes/{$publicado}/archivos", ['tipo' => 'georef_json', 'archivo' => TerritorioModuleTest::archivoJson('georef.json', ['a' => 1])])->assertCreated();
        $this->postJson("/territorio-paquetes/{$publicado}/validar")->assertJsonPath('data.estado', 'validado');
        $this->postJson("/territorio-paquetes/{$publicado}/publicar")->assertOk();
        $borrador = $this->postJson('/territorio-paquetes', ['version' => 'v02'])->json('data.id');
        $this->postJson("/territorio-paquetes/{$borrador}/archivos", ['tipo' => 'georef_json', 'archivo' => TerritorioModuleTest::archivoJson('georef.json', ['b' => 2])])->assertCreated();
        auth()->logout();

        $token = $this->token();
        $lista = $this->conToken($token)->getJson('/api/puente/territorio/manifiesto')->assertOk();
        $lista->assertJsonCount(1, 'paquetes')->assertJsonPath('paquetes.0.version', 'v01')->assertJsonCount(4, 'paquetes.0.archivos')
            ->assertJsonPath('paquetes.0.archivos.0.url_descarga', '/api/puente/territorio/archivos/'.PaqueteTerritorial::find($publicado)->archivos()->orderBy('id')->value('id'));
        $this->assertStringNotContainsString('"ruta"', $lista->getContent());

        $archivoPublicado = PaqueteTerritorial::find($publicado)->archivos()->where('tipo', 'georef_json')->first();
        $archivoBorrador = PaqueteTerritorial::find($borrador)->archivos()->first();
        $this->conToken($token)->get('/api/puente/territorio/archivos/'.$archivoPublicado->id)->assertOk()->assertHeader('X-Content-SHA256', $archivoPublicado->sha256);
        $this->conToken($token)->get('/api/puente/territorio/archivos/'.$archivoBorrador->id)->assertNotFound();
    }

    public function test_las_rutas_publicas_de_unity_siguen_sin_token(): void
    {
        $this->getJson('/api/escenarios')->assertOk();
        $this->getJson('/api/tecnologias')->assertOk();
    }
}
