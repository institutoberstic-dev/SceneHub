<?php

namespace Tests\Feature;

use App\Models\PuenteVersion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Publicación y descarga del instalador de la app «Puente de datos». */
class PuenteAppDescargaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
    }

    private function usuario(array $permisos): User
    {
        $usuario = User::factory()->create();
        $usuario->assignRole('usuario');
        $usuario->givePermissionTo($permisos);

        return $usuario;
    }

    private function instalador(string $nombre = 'PuenteDatos-1.0.0-instalador.exe', string $contenido = "MZ\x90\x00 instalador de prueba"): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, $contenido);
    }

    private function publicar(User $usuario, array $datos = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($usuario)->post('/puente-app/versiones', $datos + [
            'version' => '1.0.0', 'tipo' => 'instalador', 'notas' => 'Primera versión', 'archivo' => $this->instalador(),
        ], ['Accept' => 'application/json']);
    }

    public function test_permisos_del_modulo(): void
    {
        $this->getJson('/puente-app-data')->assertUnauthorized();
        $this->actingAs($this->usuario(['escenarios.leer']))->getJson('/puente-app-data')->assertForbidden();

        $descarga = $this->usuario(['puente.descargar']);
        $this->actingAs($descarga)->getJson('/puente-app-data')->assertOk()->assertJsonPath('puede_publicar', false);
        $this->publicar($descarga)->assertForbidden();
        $this->assertDatabaseCount('puente_versiones', 0);
    }

    public function test_publicar_descargar_y_retirar(): void
    {
        $publicador = $this->usuario(['puente.publicar', 'puente.descargar']);
        $contenido = "MZ\x90\x00 instalador de prueba";
        $this->publicar($publicador)->assertCreated()->assertJsonPath('data.sha256', hash('sha256', $contenido));

        $version = PuenteVersion::firstOrFail();
        $this->assertStringStartsWith('puente-app/1.0.0/', $version->ruta);
        Storage::disk('local')->assertExists($version->ruta);
        Storage::disk('public')->assertMissing($version->ruta); // nunca en el disco público

        $usuario = $this->usuario(['puente.descargar']);
        $datos = $this->actingAs($usuario)->getJson('/puente-app-data')->assertOk();
        $datos->assertJsonPath('vigentes.0.version', '1.0.0')
            ->assertJsonPath('vigentes.0.nombre_descarga', 'PuenteDatos-1.0.0-instalador.exe')
            ->assertJsonPath('versiones', []);
        $this->assertStringNotContainsString('"ruta"', $datos->getContent());

        $respuesta = $this->get($datos->json('vigentes.0.url_descarga'))->assertOk()->assertHeader('X-Content-SHA256', $version->sha256);
        $this->assertStringContainsString('PuenteDatos-1.0.0-instalador.exe', $respuesta->headers->get('Content-Disposition'));
        $this->assertSame($contenido, $respuesta->streamedContent());
        $this->assertSame(1, $version->fresh()->descargas);

        // Una versión nueva pasa a ser la vigente; la anterior sigue en el historial.
        $this->publicar($publicador, ['version' => '1.1.0', 'archivo' => $this->instalador('PuenteDatos-1.1.0.exe')])->assertCreated();
        $this->actingAs($usuario)->getJson('/puente-app-data')->assertJsonPath('vigentes.0.version', '1.1.0');

        $nueva = PuenteVersion::where('version', '1.1.0')->firstOrFail();
        $this->actingAs($publicador)->postJson("/puente-app/versiones/{$nueva->id}/retirar", ['motivo' => 'corto'])->assertUnprocessable();
        $this->actingAs($publicador)->postJson("/puente-app/versiones/{$nueva->id}/retirar", ['motivo' => 'Falla al abrir en Windows 10'])->assertOk();
        $this->actingAs($usuario)->getJson('/puente-app-data')->assertJsonPath('vigentes.0.version', '1.0.0');
        $this->actingAs($usuario)->get("/puente-app/versiones/{$nueva->id}/descargar")->assertNotFound();
        $this->actingAs($publicador)->get("/puente-app/versiones/{$nueva->id}/descargar")->assertOk(); // el publicador la conserva
        $this->actingAs($publicador)->getJson('/puente-app-data')->assertJsonCount(2, 'versiones')->assertJsonPath('versiones.0.motivo_retiro', 'Falla al abrir en Windows 10');
    }

    public function test_validaciones_del_archivo(): void
    {
        $publicador = $this->usuario(['puente.publicar']);
        $this->publicar($publicador, ['version' => 'v1'])->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->publicar($publicador, ['archivo' => $this->instalador('puente.exe', 'no es un ejecutable')])->assertUnprocessable()->assertJsonValidationErrors('archivo');
        $this->publicar($publicador, ['archivo' => $this->instalador('puente.zip', "MZ\x90")])->assertUnprocessable()->assertJsonValidationErrors('archivo');
        $this->publicar($publicador, ['tipo' => 'portable', 'archivo' => $this->instalador('puente.zip', "PK\x03\x04 zip")])->assertCreated();
        $this->publicar($publicador, ['tipo' => 'portable', 'archivo' => $this->instalador('otra.zip', "PK\x03\x04 zip")])->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->publicar($publicador)->assertCreated(); // misma versión, otro tipo
        $this->assertDatabaseCount('puente_versiones', 2);
    }

    public function test_el_administrador_puede_publicar_y_el_modulo_se_asigna_desde_usuarios(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->publicar($admin)->assertCreated();

        $persona = User::factory()->create();
        $this->actingAs($admin)->putJson("/users-update/{$persona->id}", [
            'name' => $persona->name, 'email' => $persona->email, 'role' => 'usuario', 'modules' => ['puente'], 'is_active' => true,
        ])->assertOk();
        $this->assertTrue($persona->fresh()->can('puente.descargar'));
        $this->assertFalse($persona->fresh()->can('puente.publicar'));
    }
}
