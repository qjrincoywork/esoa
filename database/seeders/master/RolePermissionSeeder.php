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
            'users.delete',

            // roles
            'roles.index',
            'roles.create',
            'roles.edit',
            'roles.delete',

            // permissions
            'permissions.index',
            'permissions.create',
            'permissions.edit',
            'permissions.delete',
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

