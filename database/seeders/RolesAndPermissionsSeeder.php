<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Create the initial roles and file permissions.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect([
            'usuarios.gestionar',
            'roles.gestionar',
            'escenarios.leer', 'escenarios.crear', 'escenarios.actualizar',
            'escenarios.eliminar', 'escenarios.invitar', 'escenarios.versionar',
            'archivos.leer', 'archivos.actualizar',
            'emociones.leer', 'emociones.cargar', 'emociones.invitar',
        ])->mapWithKeys(
            fn (string $name) => [
                $name => Permission::findOrCreate($name, 'web'),
            ]
        );

        $admin = Role::findOrCreate('admin', 'web');
        $client = Role::findOrCreate('cliente', 'web');

        $admin->syncPermissions($permissions->only([
            'usuarios.gestionar', 'roles.gestionar', 'escenarios.leer',
            'escenarios.actualizar', 'escenarios.eliminar', 'archivos.leer',
            'emociones.leer', 'emociones.cargar', 'emociones.invitar',
        ])->values());

        $client->syncPermissions($permissions->only([
            'escenarios.leer', 'escenarios.crear', 'escenarios.actualizar',
            'escenarios.eliminar', 'escenarios.invitar', 'escenarios.versionar',
            'archivos.leer', 'archivos.actualizar',
            'emociones.leer',
        ])->values());

        $scenarioManager = Role::findOrCreate('gestor_escenarios', 'web');
        $scenarioManager->syncPermissions($permissions->only([
            'escenarios.leer', 'escenarios.crear', 'escenarios.actualizar',
            'escenarios.eliminar', 'escenarios.invitar', 'escenarios.versionar',
            'archivos.leer', 'archivos.actualizar',
        ])->values());

        $emotionManager = Role::findOrCreate('gestor_emociones', 'web');
        $emotionManager->syncPermissions($permissions->only([
            'emociones.leer', 'emociones.cargar', 'emociones.invitar',
        ])->values());

        $viewer = Role::findOrCreate('consulta', 'web');
        $viewer->syncPermissions($permissions->only(['escenarios.leer', 'archivos.leer', 'emociones.leer'])->values());

        foreach (['owner', 'supervisor', 'collaborator'] as $legacyRoleName) {
            $legacyRole = Role::query()->where('name', $legacyRoleName)->where('guard_name', 'web')->first();
            if (! $legacyRole) {
                continue;
            }
            foreach ($legacyRole->users as $user) {
                $user->assignRole($client);
                $user->removeRole($legacyRole);
            }
            $legacyRole->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
