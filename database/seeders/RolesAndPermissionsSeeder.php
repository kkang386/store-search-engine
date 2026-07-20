<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view analytics', 'manage synonyms', 'manage query rules',
            'manage campaigns', 'manage stores', 'view audit log',
            'view search preview', 'manage users',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $roles = [
            'system_admin' => $permissions,
            'search_admin' => ['view analytics', 'manage synonyms', 'manage query rules', 'manage campaigns', 'view search preview'],
            'analyst'      => ['view analytics', 'view audit log', 'view search preview'],
            'read_only'    => ['view analytics', 'view search preview'],
        ];

        foreach ($roles as $roleName => $rolePerms) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($rolePerms);
        }
    }
}
