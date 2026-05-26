<?php

namespace Database\Seeders\master;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            'superadmin',
            'admin',
            'manager',
            'team_leader',
            'user'
        ];

        $permissions = [
            'dashboard',

            //users
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

            // soas
            'soas.index',
            'soas.create',
            'soas.edit',
            'soas.destroy',
            'soas.manage_file',
            'soas.find_member',
            'soas.member_files',
        ];

        try {
            foreach ($roles as $role) {
                Role::firstOrCreate(['name' => $role, 'guard_name'=> 'web']);
            }

            foreach ($permissions as $perm) {
                Permission::firstOrCreate(['name' => $perm, 'guard_name'=> 'web']);
            }

            // Assign all permissions to superadmin
            Role::where('name', 'superadmin')->first()
                ->syncPermissions(Permission::all());
        } catch (\Exception $e) {
            // Catch and handle any unexpected errors
            throw $e;
        }
    }
}

