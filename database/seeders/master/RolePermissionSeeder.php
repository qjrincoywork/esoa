<?php

namespace Database\Seeders\Master;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = [
            'superadmin',
            'admin',
            'manager',
            'team_leader',
            'user',
        ];

        $permissions = [
            'dashboard',

            // users
            'users.index',
            'users.create',
            'users.edit',
            'users.destroy',

            // roles
            'roles.index',
            'roles.create',
            'roles.edit',
            'roles.destroy',

            // permissions
            'permissions.index',
            'permissions.create',
            'permissions.edit',
            'permissions.destroy',

            // navigations
            'navigations.index',
            'navigations.create',
            'navigations.edit',
            'navigations.destroy',

            // navigation modules
            'navigation_modules.index',
            'navigation_modules.create',
            'navigation_modules.store',
            'navigation_modules.edit',
            'navigation_modules.update',
            'navigation_modules.destroy',

            // soas
            'soas.index',
            'soas.create',
            'soas.edit',
            'soas.destroy',
            'soas.manage_file',
            'soas.find_member',
            'soas.member_files',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::findByName('superadmin')->syncPermissions(Permission::all());
    }
}
