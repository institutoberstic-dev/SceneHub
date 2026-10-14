<?php

namespace Tests\Feature;

use App\Models\PuenteToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Coberturas de token para las rutas públicas de emociones y de simulación (config/api_tokens.php). */
class ApiTokenCoberturaTest extends TestCase
{
    use RefreshDatabase;

    /** Todas las rutas GET de la cobertura «simulacion» (el escenario 999 no existe). */
    private const SIMULACION = [
        '/api/escenarios',
        '/api/tecnologias',
        '/api/escenarios/999',
        '/api/escenarios/999/resultados',
        '/api/escenarios/999/resultados-simulacion',
        '/api/escenarios/999/resultados-solares',
        '/api/escenarios/999/simulacion',
        '/api/escenarios/999/solar',
        '/api/escenarios/999/versiones-datos?tipo=simulacion',
    ];

    private const EMOCIONES = ['/api/emociones', '/api/emociones/10'];

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response(['meetings' => []])]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/public/unity'));
        parent::tearDown();
    }

    private function token(string $cobertura): string
    {
        return PuenteToken::emitir("Cliente {$cobertura}", null, $cobertura)[1];
    }

    private function con(string $token): self
    {
        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    private function modo(string $cobertura, string $modo): void
    {
        config(["api_tokens.coberturas.{$cobertura}.modo" => $modo]);
    }

    public function test_modo_opcional_por_defecto_responde_igual_que_antes_sin_token(): void
    {
        $this->assertSame('opcional', config('api_tokens.coberturas.simulacion.modo'));
        $this->assertSame('opcional', config('api_tokens.coberturas.emociones.modo'));
        $this->getJson('/api/escenarios')->assertOk()->assertHeaderMissing('WWW-Authenticate');
        $this->getJson('/api/tecnologias')->assertOk();
        $this->getJson('/api/emociones')->assertOk();
        $this->getJson('/api/escenarios/999')->assertNotFound();
    }

    public function test_modo_opcional_valida_el_token_cuando_se_envia(): void
    {
        $simulacion = $this->token('simulacion');
        $this->con($simulacion)->getJson('/api/escenarios')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertNotNull(PuenteToken::where('alcance', 'simulacion')->value('ultimo_uso_en'));
        $this->flushHeaders();

        $this->con('puente_inventado')->getJson('/api/escenarios')->assertUnauthorized();
        $this->flushHeaders();
        $this->con($simulacion)->getJson('/api/emociones')->assertForbidden();
    }

    public function test_simulacion_obligatoria_exige_token_en_todas_sus_rutas(): void
    {
        $this->modo('simulacion', 'obligatorio');
        foreach (self::SIMULACION as $ruta) {
            $this->getJson($ruta)->assertUnauthorized();
        }
        $simulacion = $this->token('simulacion');
        $this->con($simulacion)->getJson('/api/escenarios')->assertOk();
        $this->con($simulacion)->getJson('/api/tecnologias')->assertOk();
        $this->con($simulacion)->getJson('/api/escenarios/999')->assertNotFound();
        $this->flushHeaders();

        foreach (['emociones', 'puente'] as $otra) {
            $this->con($this->token($otra))->getJson('/api/escenarios')->assertForbidden();
            $this->flushHeaders();
        }
        $this->getJson('/api/emociones')->assertOk();
    }

    public function test_emociones_obligatoria_exige_su_propio_token(): void
    {
        $this->modo('emociones', 'obligatorio');
        foreach (self::EMOCIONES as $ruta) {
            $this->getJson($ruta)->assertUnauthorized();
        }
        $this->con($this->token('emociones'))->getJson('/api/emociones')->assertOk()->assertJsonPath('total_webinars', 0);
        $this->flushHeaders();
        $this->con($this->token('simulacion'))->getJson('/api/emociones')->assertForbidden();
        $this->flushHeaders();
        $this->getJson('/api/escenarios')->assertOk();
    }

    public function test_un_modo_desconocido_se_trata_como_obligatorio(): void
    {
        $this->modo('simulacion', 'obligatoriO ');
        $this->getJson('/api/escenarios')->assertUnauthorized();
        $this->modo('simulacion', 'si');
        $this->getJson('/api/escenarios')->assertUnauthorized();
        $this->modo('simulacion', ' Opcional ');
        $this->getJson('/api/escenarios')->assertOk();
    }

    public function test_el_puente_sigue_obligatorio_y_acepta_los_tokens_historicos(): void
    {
        $this->modo('puente', 'opcional');
        $this->getJson('/api/puente/v1/estado')->assertUnauthorized();

        $historico = 'puente_'.str_repeat('a', 64);
        PuenteToken::create(['nombre' => 'PC antiguo', 'token_hash' => PuenteToken::hash($historico), 'alcance' => 'lectura']);
        $this->con($historico)->getJson('/api/puente/v1/estado')->assertOk();
        $this->flushHeaders();

        $this->con($this->token('simulacion'))->getJson('/api/puente/v1/estado')->assertForbidden();
    }

    public function test_los_tokens_son_de_solo_lectura_y_la_carga_de_imagenes_de_unity_no_cambia(): void
    {
        $this->modo('simulacion', 'obligatorio');
        $this->con($this->token('simulacion'))->postJson('/api/escenarios')->assertStatus(405);
        $this->flushHeaders();

        $this->post('/api/unity/imagenes', ['imagen' => UploadedFile::fake()->image('captura.png')], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('ok', true);
    }

    public function test_comando_crea_tokens_por_cobertura_y_lista_el_modo(): void
    {
        $this->assertSame(0, Artisan::call('puente:token-crear', ['nombre' => 'Unity gemelo', '--alcance' => 'simulacion']));
        $this->assertMatchesRegularExpression('/puente_[a-f0-9]{64}/', Artisan::output());
        $this->assertSame('simulacion', PuenteToken::firstOrFail()->alcance);

        $this->assertSame(0, Artisan::call('puente:token-crear', ['nombre' => 'PC']));
        $this->assertSame('puente', PuenteToken::latest('id')->firstOrFail()->alcance);

        $this->assertSame(1, Artisan::call('puente:token-crear', ['nombre' => 'X', '--alcance' => 'todo']));
        $this->assertSame(2, PuenteToken::count());

        $this->modo('emociones', 'obligatorio');
        $this->assertSame(0, Artisan::call('puente:token-listar'));
        $salida = Artisan::output();
        $this->assertMatchesRegularExpression('/simulacion\s*\|\s*opcional/', $salida);
        $this->assertMatchesRegularExpression('/emociones\s*\|\s*obligatorio/', $salida);
        $this->assertStringContainsString('Unity gemelo', $salida);
    }
}
