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
            'archivos.leer',
            'archivos.crear',
            'archivos.actualizar',
            'archivos.eliminar',
        ])->mapWithKeys(
            fn (string $name) => [
                $name => Permission::findOrCreate($name, 'web'),
            ]
        );

        $supervisor = Role::findOrCreate('supervisor', 'web');
        $admin = Role::findOrCreate('admin', 'web');

        $supervisor->syncPermissions([
            $permissions->get('archivos.leer'),
        ]);

        $admin->syncPermissions($permissions->values());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
