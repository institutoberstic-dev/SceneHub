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
            'escenarios.leer',
            'escenarios.crear',
            'escenarios.actualizar',
            'escenarios.eliminar',
            'escenarios.invitar',
            'escenarios.versionar',
            'archivos.leer',
            'archivos.actualizar',
        ])->mapWithKeys(
            fn (string $name) => [
                $name => Permission::findOrCreate($name, 'web'),
            ]
        );

        $admin = Role::findOrCreate('admin', 'web');
        $client = Role::findOrCreate('cliente', 'web');

        $admin->syncPermissions($permissions->values());

        $client->syncPermissions($permissions->only([
            'escenarios.leer', 'escenarios.crear', 'escenarios.actualizar',
            'escenarios.eliminar', 'escenarios.invitar', 'escenarios.versionar',
            'archivos.leer', 'archivos.actualizar',
        ])->values());

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
