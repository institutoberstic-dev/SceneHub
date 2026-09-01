<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
            '/api/features',
            '/api/simu-solars',
            '/api/users',
            '/api/roles',
        ] as $path) {
            $this->getJson($path)
                ->assertOk()
                ->assertJsonIsArray();
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

    public function test_only_scenario_owner_or_admin_can_assign_members(): void
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

        $otherOwner = User::factory()->create();
        $otherOwner->assignRole('cliente');
        $this->actingAs($otherOwner)->postJson("/escenarios/{$scenarioId}/members", [
            'email' => $member->email,
            'access_level' => 'editor',
        ])->assertForbidden();
    }

    public function test_assigned_user_can_upload_a_versioned_result(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('cliente');

        $scenarioId = $this->actingAs($owner)->postJson('/escenarios-store', [
            'nombre' => 'Escenario con resultados',
            'descripcion' => 'Seguimiento de resultados',
        ])->json('data.id');

        $archive = storage_path("app/public/escenario-{$scenarioId}-v1.zip");

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
        } finally {
            File::delete($archive);
        }
    }

    public function test_owner_can_update_scenario_and_create_next_minor_version(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('cliente');

        $scenarioId = $this->actingAs($owner)->post('/escenarios-store', [
            'nombre' => 'Escenario original',
            'descripcion' => 'Primera versión',
            'archivo' => UploadedFile::fake()->create('base.json', 2, 'application/json'),
        ])->json('data.id');

        $baseArchive = storage_path("app/public/escenario-{$scenarioId}-v1.zip");
        $updateArchive = storage_path("app/public/escenario-{$scenarioId}-v1.1.zip");

        try {
            $this->post('/escenarios/'.$scenarioId, [
                '_method' => 'PUT',
                'nombre' => 'Escenario actualizado',
                'descripcion' => 'Segunda entrega',
                'estado' => 'Activo',
                'archivo' => UploadedFile::fake()->create('actualizacion.json', 2, 'application/json'),
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
        } finally {
            File::delete([$baseArchive, $updateArchive]);
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
