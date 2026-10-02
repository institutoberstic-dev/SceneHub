<?php

namespace Tests\Feature;

use App\Models\Escenario;
use App\Models\Tecnologia;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TecnologiasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ScenarioTechnologiesTest extends TestCase
{
    use RefreshDatabase;

    private string $temporaryStorage;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporaryStorage = sys_get_temp_dir().'/technology-test-'.bin2hex(random_bytes(8));
        $this->app->useStoragePath($this->temporaryStorage);
        $this->seed([RolesAndPermissionsSeeder::class, TecnologiasSeeder::class]);
        $this->owner = User::factory()->create();
        $this->owner->assignRole('cliente');
        $this->actingAs($this->owner);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryStorage);
        parent::tearDown();
    }

    private function create(string $name, array $technologies = []): \Illuminate\Testing\TestResponse
    {
        $payload = ['nombre' => $name, 'descripcion' => 'Escenario de simulación', 'archivo' => UploadedFile::fake()->create('informe.docx', 10)];
        if ($technologies !== []) {
            $payload['tecnologias'] = $technologies;
        }

        return $this->post('/escenarios-store', $payload, ['Accept' => 'application/json']);
    }

    public function test_seeder_is_idempotent_and_keeps_codes_stable(): void
    {
        $this->seed(TecnologiasSeeder::class);
        $this->assertDatabaseCount('tecnologias', count(TecnologiasSeeder::CATALOGO));
        $this->assertDatabaseHas('tecnologias', ['codigo' => 'PANEL_SOLAR', 'nombre' => 'Paneles solares', 'activo' => true]);
        $this->assertDatabaseHas('tecnologias', ['codigo' => 'DIESEL']);
        $this->assertDatabaseCount('categorias_tecnologia', count(TecnologiasSeeder::CATEGORIAS));
        $this->assertSame(0, Tecnologia::whereNull('categoria_id')->count());
        $this->assertSame('TRATAMIENTO_AGUA', Tecnologia::where('codigo', 'DESALINIZADORA')->first()->categoria->codigo);
        $this->assertSame(['ELECTROLIZADORA', 'HABER-BOSH'], Tecnologia::whereHas('categoria', fn ($q) => $q->where('codigo', 'HIDROGENO_AMONIACO'))->ordenadas()->pluck('codigo')->all());
    }

    public function test_catalog_is_available_for_the_form_and_for_unity(): void
    {
        Tecnologia::where('codigo', 'GEOPOLIMEROS')->update(['activo' => false]);

        $form = $this->getJson('/tecnologias-data')->assertOk()->json();
        $this->assertSame(['codigo' => 'PANEL_SOLAR', 'nombre' => 'Paneles solares', 'categoria' => ['codigo' => 'GENERACION_ENERGIA', 'nombre' => 'Generación de energía']], $form[0]);
        $this->assertSame(['PANEL_SOLAR', 'TURBINAS_EOLICAS', 'DIESEL', 'DESALINIZADORA'], array_slice(array_column($form, 'codigo'), 0, 4));
        $this->assertNotContains('GEOPOLIMEROS', array_column($form, 'codigo'));

        auth()->logout();
        $this->getJson('/tecnologias-data')->assertUnauthorized();
        $public = $this->getJson('/api/tecnologias')->assertOk()->json();
        $this->assertCount(count(TecnologiasSeeder::CATALOGO), $public);
        $geopolimeros = collect($public)->firstWhere('codigo', 'GEOPOLIMEROS');
        $this->assertFalse($geopolimeros['activo']);
        $this->assertSame('VALORIZACION_RESIDUOS', $geopolimeros['categoria']['codigo']);
    }

    public function test_scenario_stores_the_selected_technologies_and_exposes_their_codes(): void
    {
        $response = $this->create('Escenario 1', ['PANEL_SOLAR', 'DESALINIZADORA', 'DIESEL'])
            ->assertCreated()
            ->assertJsonCount(3, 'data.tecnologias')
            ->assertJsonPath('data.tecnologias.0', ['codigo' => 'PANEL_SOLAR', 'nombre' => 'Paneles solares', 'categoria' => ['codigo' => 'GENERACION_ENERGIA', 'nombre' => 'Generación de energía']]);
        $id = $response->json('data.id');

        $this->assertDatabaseCount('escenario_tecnologia', 3);
        $codes = fn ($json) => array_column($json, 'codigo');
        $this->assertSame(['PANEL_SOLAR', 'DIESEL', 'DESALINIZADORA'], $codes($this->getJson("/escenarios-data/$id")->assertOk()->json('tecnologias')));
        $this->assertSame(['PANEL_SOLAR', 'DIESEL', 'DESALINIZADORA'], $codes($this->getJson('/escenarios-data')->assertOk()->json('0.tecnologias')));

        auth()->logout();
        $this->getJson("/api/escenarios/$id")->assertOk()
            ->assertJsonPath('tecnologias.0.codigo', 'PANEL_SOLAR')
            ->assertJsonPath('tecnologias.2.categoria.codigo', 'TRATAMIENTO_AGUA')
            ->assertJsonMissingPath('tecnologias.0.categoria_id')
            ->assertJsonMissingPath('tecnologias.0.pivot')
            ->assertJsonMissingPath('tecnologias.0.id');
        $this->getJson('/api/escenarios')->assertOk()->assertJsonPath('0.tecnologias.2.codigo', 'DESALINIZADORA');
    }

    public function test_scenario_can_be_created_without_technologies_and_one_technology_is_reused(): void
    {
        $this->create('Sin tecnologías')->assertCreated()->assertJsonCount(0, 'data.tecnologias');
        $this->create('Escenario 2', ['PANEL_SOLAR'])->assertCreated();
        $this->create('Escenario 3', ['PANEL_SOLAR'])->assertCreated();
        $this->assertSame(2, Tecnologia::where('codigo', 'PANEL_SOLAR')->first()->escenarios()->count());
    }

    public function test_free_text_unknown_duplicated_or_inactive_technologies_are_rejected(): void
    {
        $this->create('Texto libre', ['Paneles solares'])->assertUnprocessable()
            ->assertJsonValidationErrors(['tecnologias.0' => 'La tecnología «Paneles solares» no pertenece al catálogo.']);
        $this->create('Duplicada', ['PANEL_SOLAR', 'PANEL_SOLAR'])->assertUnprocessable()->assertJsonValidationErrors(['tecnologias.0', 'tecnologias.1']);
        $this->post('/escenarios-store', ['nombre' => 'Cadena', 'descripcion' => 'x', 'tecnologias' => 'PANEL_SOLAR', 'archivo' => UploadedFile::fake()->create('a.docx', 1)], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('tecnologias');

        Tecnologia::where('codigo', 'GEOPOLIMEROS')->update(['activo' => false]);
        $this->create('Inactiva', ['GEOPOLIMEROS'])->assertUnprocessable()->assertJsonValidationErrors('tecnologias');

        $this->assertDatabaseCount('esceanarios', 0);
        $this->assertDatabaseCount('escenario_tecnologia', 0);
    }

    public function test_update_synchronises_technologies_only_when_the_field_is_sent(): void
    {
        $id = $this->create('Escenario 1', ['PANEL_SOLAR', 'DESALINIZADORA'])->assertCreated()->json('data.id');
        $base = ['nombre' => 'Escenario 1', 'descripcion' => 'Actualizado', 'estado' => 'Activo'];

        // Sin el campo: se conservan.
        $this->putJson("/escenarios/$id", $base)->assertOk()->assertJsonCount(2, 'data.tecnologias');

        // Con el campo: se reemplazan.
        $this->putJson("/escenarios/$id", [...$base, 'tecnologias' => ['DIESEL']])->assertOk()
            ->assertJsonCount(1, 'data.tecnologias')->assertJsonPath('data.tecnologias.0.codigo', 'DIESEL');

        // Formulario multipart con el campo vacío: se quitan todas.
        $this->post("/escenarios/$id", [...$base, '_method' => 'PUT', 'tecnologias' => ''], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonCount(0, 'data.tecnologias');
        $this->assertDatabaseCount('escenario_tecnologia', 0);
    }

    public function test_a_deactivated_technology_is_kept_when_the_scenario_is_edited(): void
    {
        $id = $this->create('Escenario 3', ['PANEL_SOLAR', 'GEOPOLIMEROS'])->assertCreated()->json('data.id');
        Tecnologia::where('codigo', 'GEOPOLIMEROS')->update(['activo' => false]);

        $this->putJson("/escenarios/$id", ['nombre' => 'Escenario 3', 'descripcion' => 'x', 'estado' => 'Activo', 'tecnologias' => ['PANEL_SOLAR', 'GEOPOLIMEROS']])
            ->assertOk()->assertJsonCount(2, 'data.tecnologias');
        $this->assertTrue(Escenario::find($id)->tecnologias->pluck('codigo')->contains('GEOPOLIMEROS'));
    }

    public function test_admin_cannot_change_technologies_and_members_cannot_without_access(): void
    {
        $id = $this->create('Escenario 1', ['PANEL_SOLAR'])->assertCreated()->json('data.id');

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin)->putJson("/escenarios/$id", ['nombre' => 'Escenario uno', 'tecnologias' => ['DIESEL']])->assertOk()
            ->assertJsonPath('data.tecnologias.0.codigo', 'PANEL_SOLAR');

        $stranger = User::factory()->create();
        $stranger->assignRole('cliente');
        $this->actingAs($stranger)->putJson("/escenarios/$id", ['nombre' => 'X', 'descripcion' => 'x', 'estado' => 'Activo', 'tecnologias' => []])->assertForbidden();
        $this->assertSame(['PANEL_SOLAR'], Escenario::find($id)->tecnologias->pluck('codigo')->all());
    }

    public function test_deleting_a_scenario_removes_its_associations_but_not_the_catalog(): void
    {
        $id = $this->create('Temporal', ['PANEL_SOLAR'])->assertCreated()->json('data.id');
        $this->deleteJson("/escenarios/$id")->assertOk();
        $this->assertDatabaseCount('escenario_tecnologia', 0);
        $this->assertDatabaseHas('tecnologias', ['codigo' => 'PANEL_SOLAR']);
    }
}
