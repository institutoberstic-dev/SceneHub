<?php

namespace Tests\Feature;

use App\Models\Escenario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UnityImageUploadTest extends TestCase
{
    use RefreshDatabase;

    // PNG válido de 1x1 px (no requiere la extensión GD).
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private function png(string $name = 'captura.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(self::PNG));
    }

    public function test_unity_can_upload_an_image_without_session_or_csrf(): void
    {
        Storage::fake('public');
        $date = now()->format('Y-m-d');

        // Sin cabecera Accept, como lo envía UnityWebRequest por defecto.
        $response = $this->post('/api/unity/imagenes', ['imagen' => $this->png('Captura Escena 1.png')]);

        $response->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('escenario_id', null)
            ->assertJsonPath('imagenes.0.nombre_original', 'Captura Escena 1.png')
            ->assertJsonPath('imagenes.0.mime_type', 'image/png');

        $path = $response->json('imagenes.0.ruta');
        $this->assertStringStartsWith("unity/general/{$date}/captura-escena-1_", $path);
        $this->assertStringEndsWith('.png', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_multiple_images_are_grouped_by_scenario(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $escenario = Escenario::create(['nombre' => 'Planta', 'owner_id' => $owner->id, 'estado' => 'Activo', 'descripcion' => 'Prueba', 'versiones' => 1.0]);

        $response = $this->post('/api/unity/imagenes', [
            'escenario_id' => $escenario->id,
            'imagenes' => [$this->png('a.png'), $this->png('b.png')],
        ])->assertCreated()->assertJsonPath('total', 2)->assertJsonPath('escenario_id', $escenario->id);

        foreach ($response->json('imagenes') as $image) {
            $this->assertStringStartsWith("unity/{$escenario->id}/", $image['ruta']);
            Storage::disk('public')->assertExists($image['ruta']);
        }
    }

    public function test_invalid_uploads_are_rejected_with_json(): void
    {
        Storage::fake('public');

        $this->post('/api/unity/imagenes', [])
            ->assertStatus(422)->assertJsonPath('ok', false)->assertJsonStructure(['mensaje', 'errores']);

        // Un archivo que no es imagen, aunque se llame .png.
        $fake = UploadedFile::fake()->createWithContent('falso.png', '<?php echo "hola";');
        $this->post('/api/unity/imagenes', ['imagen' => $fake])->assertStatus(422);

        $this->post('/api/unity/imagenes', ['imagen' => $this->png(), 'escenario_id' => 999])
            ->assertStatus(422)->assertJsonPath('mensaje', 'El escenario indicado no existe.');

        $tooMany = array_map(fn ($i) => $this->png("{$i}.png"), range(1, 11));
        $this->post('/api/unity/imagenes', ['imagenes' => $tooMany])->assertStatus(422);

        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
