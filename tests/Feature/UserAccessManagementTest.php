<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
