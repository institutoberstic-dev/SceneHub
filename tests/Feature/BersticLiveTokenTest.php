<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as PeticionHttp;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** SceneHub envía el token de Berstic Live (desde el .env) al pedir el catálogo de webinars. */
class BersticLiveTokenTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://bersticlive.org/api/meetings';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function lector(bool $admin = false): User
    {
        $usuario = User::factory()->create();
        $usuario->assignRole($admin ? 'admin' : 'usuario');
        if (! $admin) {
            $usuario->givePermissionTo('emociones.leer');
        }

        return $usuario;
    }

    public function test_envia_el_token_configurado_y_lista_los_webinars(): void
    {
        config(['services.bersticlive.token' => 'token-de-prueba-123']);
        Http::fake([self::URL => Http::response(['meetings' => [['id' => 7, 'title' => 'Webinar con token']]])]);

        $this->actingAs($this->lector())->getJson('/emociones-data')->assertOk()
            ->assertJsonPath('meetings.0.title', 'Webinar con token')
            ->assertJsonPath('aviso_catalogo', null);

        Http::assertSent(fn (PeticionHttp $peticion) => $peticion->url() === self::URL
            && $peticion->hasHeader('Authorization', 'Bearer token-de-prueba-123'));
    }

    public function test_sin_token_no_envia_cabecera_y_avisa_al_administrador_si_berstic_lo_pide(): void
    {
        config(['services.bersticlive.token' => null]);
        Http::fake([self::URL => Http::response(['message' => 'Unauthenticated.'], 401)]);

        $this->actingAs($this->lector(admin: true))->getJson('/emociones-data')->assertOk()
            ->assertJsonPath('meetings', [])
            ->assertJsonPath('aviso_catalogo', fn ($texto) => str_contains($texto, 'BERSTICLIVE_API_TOKEN') && str_contains($texto, 'pide un token'));

        Http::assertSent(fn (PeticionHttp $peticion) => ! $peticion->hasHeader('Authorization'));
    }

    public function test_token_rechazado_avisa_sin_mostrar_el_token(): void
    {
        config(['services.bersticlive.token' => 'token-vencido-456']);
        Http::fake([self::URL => Http::response(['message' => 'Forbidden'], 403)]);

        $admin = $this->actingAs($this->lector(admin: true))->getJson('/emociones-data')->assertOk();
        $this->assertStringContainsString('rechazó el token', $admin->json('aviso_catalogo'));
        $this->assertStringNotContainsString('token-vencido-456', $admin->getContent());

        // Quien no administra ve un aviso general, sin detalles de configuración.
        $lector = $this->actingAs($this->lector())->getJson('/emociones-data')->assertOk();
        $this->assertStringNotContainsString('BERSTICLIVE', $lector->json('aviso_catalogo'));
    }

    public function test_la_url_se_puede_cambiar_desde_el_env(): void
    {
        config(['services.bersticlive.url' => 'https://otro.bersticlive.org/api/v2/meetings', 'services.bersticlive.token' => 'x']);
        Http::fake(['otro.bersticlive.org/*' => Http::response(['meetings' => [['id' => 3, 'title' => 'Desde otra URL']]])]);

        $this->actingAs($this->lector())->getJson('/emociones-data')->assertOk()->assertJsonPath('meetings.0.title', 'Desde otra URL');
    }
}
