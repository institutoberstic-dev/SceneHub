<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ApplicationShellTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_root_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_react_pages_are_served_by_laravel(): void
    {
        $this->actingAs($this->admin);

        foreach ([
            '/login',
            '/dashboard',
            '/escenarios',
            '/resultados',
            '/users-list',
            '/roles-list',
        ] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertViewIs('app');
        }
    }

    public function test_react_refresh_preamble_is_present_when_vite_is_hot(): void
    {
        $hotFile = public_path('hot');
        $previousHotUrl = file_exists($hotFile) ? file_get_contents($hotFile) : null;

        file_put_contents($hotFile, 'http://127.0.0.1:5173');

        try {
            $this->get('/login')
                ->assertOk()
                ->assertSee('@react-refresh', false)
                ->assertSee('injectIntoGlobalHook', false);
        } finally {
            if ($previousHotUrl === null) {
                @unlink($hotFile);
            } else {
                file_put_contents($hotFile, $previousHotUrl);
            }
        }
    }

    public function test_unknown_frontend_route_is_served_by_the_spa(): void
    {
        $this->actingAs($this->admin);

        $this->get('/frontend/deep-link')
            ->assertOk()
            ->assertViewIs('app');
    }

    public function test_csrf_token_can_be_refreshed_for_react_requests(): void
    {
        $this->getJson('/csrf/refresh')
            ->assertOk()
            ->assertJsonStructure(['token']);
    }

    public function test_read_endpoints_return_json_collections(): void
    {
        $this->actingAs($this->admin);

        foreach ([
            '/api/escenarios',
            '/api/users',
            '/api/roles',
        ] as $path) {
            $this->getJson($path)
                ->assertOk()
                ->assertJsonIsArray();
        }
    }

    public function test_retired_telemetry_endpoints_explain_replacement_without_querying_removed_tables(): void
    {
        foreach (['/api/features', '/api/simu-solars'] as $path) {
            $this->getJson($path)->assertStatus(410)->assertJsonPath('code', 'LEGACY_ENDPOINT_RETIRED')->assertJsonStructure(['message', 'endpoints']);
        }
        foreach (['/api/send-features', '/api/send-simu-sol'] as $path) {
            $this->postJson($path, [])->assertStatus(410)->assertJsonPath('code', 'LEGACY_ENDPOINT_RETIRED');
        }
    }

    public function test_owner_can_create_a_scenario_but_cannot_manage_global_roles(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('cliente');

        $this->actingAs($owner)
            ->postJson('/escenarios-store', [
                'nombre' => 'Escenario del owner',
                'descripcion' => 'Contenido de prueba',
            ])
            ->assertCreated()
            ->assertJsonPath('data.owner_id', $owner->id);

        $this->assertDatabaseHas('escenarios_users', [
            'user_id' => $owner->id,
            'access_level' => 'owner',
        ]);

        $this->get('/roles-list')->assertForbidden();
    }

    public function test_guest_cannot_open_protected_spa_routes(): void
    {
        auth()->logout();

        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/frontend/deep-link')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/log-out')
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_only_scenario_owner_can_assign_and_remove_members(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('cliente');
        $member = User::factory()->create();
        $member->assignRole('cliente');

        $scenarioId = $this->actingAs($owner)->postJson('/escenarios-store', [
            'nombre' => 'Escenario compartido',
            'descripcion' => 'Prueba de accesos',
        ])->json('data.id');

        $this->postJson("/escenarios/{$scenarioId}/members", [
            'email' => $member->email,
            'access_level' => 'supervisor',
        ])->assertOk();

        $this->assertDatabaseHas('escenarios_users', [
            'escenario_id' => $scenarioId,
            'user_id' => $member->id,
            'access_level' => 'supervisor',
            'invited_by' => $owner->id,
        ]);

        $this->actingAs($this->admin)->postJson("/escenarios/{$scenarioId}/members", [
            'email' => $member->email,
            'access_level' => 'editor',
        ])->assertForbidden();

        $this->actingAs($owner)->deleteJson("/escenarios/{$scenarioId}/members/{$member->id}")
            ->assertOk();
        $this->assertDatabaseMissing('escenarios_users', [
            'escenario_id' => $scenarioId,
            'user_id' => $member->id,
        ]);
        $this->assertDatabaseHas('users', ['id' => $member->id]);

        $otherOwner = User::factory()->create();
        $otherOwner->assignRole('cliente');
        $this->actingAs($otherOwner)->postJson("/escenarios/{$scenarioId}/members", [
            'email' => $member->email,
            'access_level' => 'editor',
        ])->assertForbidden();
    }

    public function test_admin_can_only_read_rename_and_delete_scenarios(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('cliente');
        $scenarioId = $this->actingAs($owner)->postJson('/escenarios-store', [
            'nombre' => 'Nombre original',
            'descripcion' => 'Descripción del owner',
        ])->json('data.id');

        $this->actingAs($this->admin)->postJson('/escenarios-store', [
            'nombre' => 'No permitido',
            'descripcion' => 'No permitido',
        ])->assertForbidden();

        $this->getJson("/api/escenarios/{$scenarioId}")->assertOk();
        $this->putJson("/escenarios/{$scenarioId}", ['nombre' => 'Nombre corregido'])
            ->assertOk();
        $this->assertDatabaseHas('esceanarios', [
            'id' => $scenarioId,
            'nombre' => 'Nombre corregido',
            'descripcion' => 'Descripción del owner',
        ]);

        $this->deleteJson("/escenarios/{$scenarioId}")->assertOk();
        $this->assertDatabaseMissing('esceanarios', ['id' => $scenarioId]);
    }

    public function test_assigned_user_can_upload_a_versioned_result(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('cliente');

        $scenarioId = $this->actingAs($owner)->postJson('/escenarios-store', [
            'nombre' => 'Escenario con resultados',
            'descripcion' => 'Seguimiento de resultados',
        ])->json('data.id');

        $scenarioDirectory = storage_path("app/public/escenarios/{$scenarioId}-escenario-con-resultados");

        try {
            $this->post('/escenarios/'.$scenarioId.'/contenidos', [
                'nombre' => 'Resultado inicial',
                'archivo' => UploadedFile::fake()->create('resultado.json', 2, 'application/json'),
            ])->assertCreated()
                ->assertJsonPath('data.nombre', 'Resultado inicial')
                ->assertJsonPath('data.version', '1.0');

            $this->assertDatabaseHas('escenario_contenidos', [
                'escenario_id' => $scenarioId,
                'uploaded_by' => $owner->id,
                'nombre' => 'Resultado inicial',
                'tipo' => 'resultado',
            ]);
            $this->assertFileExists($scenarioDirectory.'/1.0/resultado.json');

            $this->post('/escenarios/'.$scenarioId.'/contenidos', [
                'nombre' => 'Resultado repetido',
                'archivo' => UploadedFile::fake()->create('resultado.json', 2, 'application/json'),
            ])->assertOk()
                ->assertJsonPath('message', 'El archivo no presenta cambios; no se almacenó una copia duplicada.');

            $this->assertDatabaseCount('escenario_contenidos', 1);
        } finally {
            File::deleteDirectory($scenarioDirectory);
        }
    }

    public function test_new_file_keeps_version_and_replacing_it_creates_a_cumulative_snapshot(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('cliente');

        $scenarioId = $this->actingAs($owner)->post('/escenarios-store', [
            'nombre' => 'Escenario original',
            'descripcion' => 'Primera versión',
            'archivo' => UploadedFile::fake()->createWithContent('base.json', '{"value":1}'),
        ])->json('data.id');

        $scenarioDirectory = storage_path("app/public/escenarios/{$scenarioId}-escenario-original");

        try {
            $this->post('/escenarios/'.$scenarioId, [
                '_method' => 'PUT',
                'nombre' => 'Escenario actualizado',
                'descripcion' => 'Segunda entrega',
                'estado' => 'Activo',
                'archivo' => UploadedFile::fake()->createWithContent('adicional.json', '{"extra":true}'),
            ])->assertOk()
                ->assertJsonPath('data.versiones', 1);

            $this->assertFileExists($scenarioDirectory.'/1.0/base.json');
            $this->assertFileExists($scenarioDirectory.'/1.0/adicional.json');

            $this->post('/escenarios/'.$scenarioId, [
                '_method' => 'PUT',
                'nombre' => 'Escenario actualizado',
                'descripcion' => 'Tercera entrega',
                'estado' => 'Activo',
                'archivo' => UploadedFile::fake()->createWithContent('base.json', '{"value":2}'),
            ])->assertOk()
                ->assertJsonPath('data.versiones', 1.1);

            $this->assertDatabaseHas('esceanarios', [
                'id' => $scenarioId,
                'nombre' => 'Escenario actualizado',
                'versiones' => 1.1,
            ]);
            $this->assertDatabaseHas('escenario_contenidos', [
                'escenario_id' => $scenarioId,
                'version' => 1.1,
                'tipo' => 'actualizacion',
                'modified_by' => $owner->id,
            ]);
            $this->assertFileExists($scenarioDirectory.'/1.1/base.json');
            $this->assertFileExists($scenarioDirectory.'/1.1/adicional.json');
            $this->assertSame('{"value":1}', File::get($scenarioDirectory.'/1.0/base.json'));
            $this->assertSame('{"value":2}', File::get($scenarioDirectory.'/1.1/base.json'));

            // También debe reconocer archivos de versiones anteriores si una carpeta
            // existente no contiene una instantánea acumulativa completa.
            File::delete($scenarioDirectory.'/1.1/base.json');

            $this->post('/escenarios/'.$scenarioId, [
                '_method' => 'PUT',
                'nombre' => 'Escenario actualizado',
                'descripcion' => 'Cuarta entrega',
                'estado' => 'Activo',
                'archivo' => UploadedFile::fake()->createWithContent('base.json', '{"value":3}'),
            ])->assertOk()
                ->assertJsonPath('data.versiones', 1.2);

            $this->assertFileExists($scenarioDirectory.'/1.2/base.json');
            $this->assertFileExists($scenarioDirectory.'/1.2/adicional.json');
            $this->assertSame('{"value":3}', File::get($scenarioDirectory.'/1.2/base.json'));

            $contentCount = DB::table('escenario_contenidos')
                ->where('escenario_id', $scenarioId)
                ->count();

            $this->post('/escenarios/'.$scenarioId, [
                '_method' => 'PUT',
                'nombre' => 'Escenario actualizado',
                'descripcion' => 'Entrega sin cambios de archivo',
                'estado' => 'Activo',
                'archivo' => UploadedFile::fake()->createWithContent('base.json', '{"value":3}'),
            ])->assertOk()
                ->assertJsonPath('data.versiones', 1.2)
                ->assertJsonPath('message', 'Escenario actualizado; el archivo no cambió y no se almacenó nuevamente.');

            $this->assertSame(
                $contentCount,
                DB::table('escenario_contenidos')->where('escenario_id', $scenarioId)->count()
            );
            $this->assertDirectoryDoesNotExist($scenarioDirectory.'/1.3');
        } finally {
            File::deleteDirectory($scenarioDirectory);
        }
    }

    public function test_scenario_permissions_follow_the_role_distribution(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('cliente');
        $supervisor = User::factory()->create();
        $supervisor->assignRole('cliente');
        $collaborator = User::factory()->create();
        $collaborator->assignRole('cliente');

        $scenarioId = $this->actingAs($owner)->postJson('/escenarios-store', [
            'nombre' => 'Escenario con equipo',
            'descripcion' => 'Prueba de distribución de permisos',
        ])->json('data.id');

        $this->postJson("/escenarios/{$scenarioId}/members", [
            'email' => $supervisor->email,
            'access_level' => 'supervisor',
        ])->assertOk();
        $this->postJson("/escenarios/{$scenarioId}/members", [
            'email' => $collaborator->email,
            'access_level' => 'editor',
        ])->assertOk();

        $this->actingAs($supervisor)->putJson("/escenarios/{$scenarioId}", [
            'nombre' => 'Escenario supervisado',
            'descripcion' => 'Actualización de metadatos permitida',
            'estado' => 'Activo',
        ])->assertOk();

        $this->post("/escenarios/{$scenarioId}/contenidos", [
            'archivo' => UploadedFile::fake()->create('reemplazo.json', 2, 'application/json'),
        ])->assertForbidden();

        $this->actingAs($collaborator)->putJson("/escenarios/{$scenarioId}", [
            'nombre' => 'Cambio no permitido',
            'descripcion' => 'No debe actualizar el escenario',
            'estado' => 'Activo',
        ])->assertForbidden();

        $this->post("/escenarios/{$scenarioId}/contenidos", [
            'archivo' => UploadedFile::fake()->create('version-no-permitida.json', 2, 'application/json'),
        ])->assertForbidden();
    }
}
