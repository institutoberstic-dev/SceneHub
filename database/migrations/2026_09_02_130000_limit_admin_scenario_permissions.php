<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $restricted = [
        'escenarios.crear',
        'escenarios.invitar',
        'escenarios.versionar',
        'archivos.actualizar',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $roleId = DB::table('roles')->where('name', 'admin')->where('guard_name', 'web')->value('id');
        if (! $roleId) {
            return;
        }

        $permissionIds = DB::table('permissions')->whereIn('name', $this->restricted)->pluck('id');
        DB::table('role_has_permissions')->where('role_id', $roleId)->whereIn('permission_id', $permissionIds)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $roleId = DB::table('roles')->where('name', 'admin')->where('guard_name', 'web')->value('id');
        if (! $roleId) {
            return;
        }

        $rows = DB::table('permissions')->whereIn('name', $this->restricted)->get()->map(
            fn ($permission) => ['role_id' => $roleId, 'permission_id' => $permission->id]
        )->all();
        if ($rows) {
            DB::table('role_has_permissions')->insertOrIgnore($rows);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
