<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAccessManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('admin');
    }

    public function test_admin_assigns_and_updates_module_access_from_the_user_record(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/users-register', [
            'name' => 'Gestora emocional',
            'email' => 'emociones@example.com',
            'password' => 'password123',
            'role' => 'usuario',
            'modules' => ['emociones'],
            'is_active' => true,
        ])->assertCreated();

        $user = User::findOrFail($response->json('data.id'));
        $this->assertTrue($user->hasRole('usuario'));
        $this->assertTrue($user->hasDirectPermission('emociones.leer'));
        $this->assertTrue($user->hasDirectPermission('emociones.cargar'));
        $this->assertFalse($user->can('escenarios.leer'));

        $this->putJson("/users-update/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'password' => '',
            'role' => 'usuario',
            'modules' => ['escenarios'],
            'is_active' => false,
        ])->assertOk();

        $user->refresh();
        $this->assertFalse($user->is_active);
        $this->assertTrue($user->hasDirectPermission('escenarios.leer'));
        $this->assertFalse($user->can('emociones.leer'));
    }

    public function test_module_routes_follow_the_permissions_assigned_by_admin(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('usuario');
        $user->givePermissionTo(['emociones.leer']);

        $this->actingAs($user)->get('/emociones')->assertOk();
        $this->get('/escenarios')->assertForbidden();
        $this->get('/resultados')->assertForbidden();
    }

    public function test_disabled_users_are_rejected_and_the_last_admin_is_protected(): void
    {
        $disabled = User::factory()->create([
            'email' => 'disabled@example.com',
            'password' => Hash::make('password123'),
            'is_active' => false,
        ]);
        $disabled->assignRole('usuario');

        $this->postJson('/log-in', [
            'email' => $disabled->email,
            'password' => 'password123',
        ])->assertForbidden()->assertJsonPath('code', 'ACCOUNT_DISABLED');

        $this->actingAs($this->admin)->putJson("/users-update/{$this->admin->id}", [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'password' => '',
            'role' => 'usuario',
            'modules' => ['escenarios'],
            'is_active' => false,
        ])->assertUnprocessable()->assertJsonPath('message', 'Debe permanecer al menos un administrador activo.');
    }

    public function test_users_with_scenarios_module_can_be_invited_to_a_scenario(): void
    {
        $scenarioPermissions = [
            'escenarios.leer', 'escenarios.crear', 'escenarios.actualizar',
            'escenarios.eliminar', 'escenarios.invitar', 'escenarios.versionar',
            'archivos.leer', 'archivos.actualizar',
        ];

        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole('usuario');
        $owner->givePermissionTo($scenarioPermissions);

        $member = User::factory()->create(['is_active' => true]);
        $member->assignRole('usuario');
        $member->givePermissionTo($scenarioPermissions);

        $emotionsOnly = User::factory()->create(['is_active' => true]);
        $emotionsOnly->assignRole('usuario');
        $emotionsOnly->givePermissionTo(['emociones.leer']);

        $scenarioId = $this->actingAs($owner)->postJson('/escenarios-store', [
            'nombre' => 'Escenario con usuario de módulo',
            'descripcion' => 'Regresión de invitaciones',
            'archivo' => UploadedFile::fake()->create('informe.docx', 10),
        ])->json('data.id');

        $this->postJson("/escenarios/{$scenarioId}/members", [
            'email' => $member->email,
            'access_level' => 'supervisor',
        ])->assertOk();

        $this->assertDatabaseHas('escenarios_users', [
            'escenario_id' => $scenarioId,
            'user_id' => $member->id,
            'access_level' => 'supervisor',
        ]);

        $this->postJson("/escenarios/{$scenarioId}/members", [
            'email' => $emotionsOnly->email,
            'access_level' => 'supervisor',
        ])->assertUnprocessable()->assertJsonPath('message', 'La cuenta no tiene habilitado el módulo de Escenarios.');

        $this->postJson("/escenarios/{$scenarioId}/members", [
            'email' => $this->admin->email,
            'access_level' => 'supervisor',
        ])->assertUnprocessable();
    }
}
