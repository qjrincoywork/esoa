<?php

namespace Database\Seeders\master;

use Illuminate\Database\Seeder;
use App\Models\NavigationModule;

class NavigationModuleSeeder extends Seeder
{
    public function run()
    {
        $modules = [
            [
                'navigation_id' => 1,
                'permission_id' => 1,
                'name' => 'Admin Dashboard',
                'icon' => 'SquareTerminal',
                'url' => '/dashboard',
                'slug' => 'dashboard',
                'ref_id' => null,
                'order_number'=> 1,
                'status' => 1,
                'created_by' => 1,
            ],
            //Users
            [
                'navigation_id' => 1,
                'permission_id' => 2,
                'name' => 'User List',
                'icon' => 'Users',
                'url' => '/users',
                'slug' => 'users-index',
                'ref_id' => null,
                'order_number'=> 2,
                'status' => 1,
                'created_by' => 1,
            ],
            [
                'navigation_id' => 1,
                'permission_id' => 3,
                'name' => 'Create User',
                'icon' => 'UserPlus',
                'url' => '/users/create',
                'slug' => 'users-create',
                'ref_id' => 2,
                'order_number'=> 3,
                'status' => 1,
                'created_by' => 1,
            ],
            [
                'navigation_id' => 1,
                'permission_id' => 4,
                'name' => 'Edit User',
                'icon' => 'Pencil',
                'url' => '/users/{id}/edit',
                'slug' => 'users-edit',
                'ref_id' => 2,
                'order_number'=> 4,
                'status' => 1,
                'created_by' => 1,
            ],
            [
                'navigation_id' => 1,
                'permission_id' => 5,
                'name' => 'Delete User',
                'icon' => 'Trash2',
                'url' => '/users/destroy',
                'slug' => 'users-destroy',
                'ref_id' => 2,
                'order_number'=> 5,
                'status' => 1,
                'created_by' => 1,
            ],
            //roles
            [
                'navigation_id' => 1,
                'permission_id' => 6,
                'name' => 'Role List',
                'icon' => 'Lock',
                'url' => '/roles',
                'slug' => 'roles-index',
                'ref_id' => null,
                'order_number'=> 6,
                'status' => 1,
                'created_by' => 1,
            ],
            [
                'navigation_id' => 1,
                'permission_id' => 7,
                'name' => 'Create Role',
                'icon' => 'UserPlus',
                'url' => '/roles/create',
                'slug' => 'roles-create',
                'ref_id' => 6,
                'order_number'=> 7,
                'status' => 1,
                'created_by' => 1,
            ],
            [
                'navigation_id' => 1,
                'permission_id' => 8,
                'name' => 'Edit Role',
                'icon' => 'Pencil',
                'url' => '/roles/{id}/edit',
                'slug' => 'roles-edit',
                'ref_id' => 6,
                'order_number'=> 8,
                'status' => 1,
                'created_by' => 1,
            ],
            [
                'navigation_id' => 1,
                'permission_id' => 9,
                'name' => 'Delete Role',
                'icon' => 'Trash2',
                'url' => '/roles/destroy',
                'slug' => 'roles-destroy',
                'ref_id' => 6,
                'order_number'=> 9,
                'status' => 1,
                'created_by' => 1,
            ],
            //permissions
            [
                'navigation_id' => 1,
                'permission_id' => 10,
                'name' => 'Permission List',
                'icon' => 'Key',
                'url' => '/permissions',
                'slug' => 'permissions-index',
                'ref_id' => null,
                'order_number'=> 10,
                'status' => 1,
                'created_by' => 1,
            ],
            [
                'navigation_id' => 1,
                'permission_id' => 11,
                'name' => 'Create Permission',
                'icon' => 'UserPlus',
                'url' => '/permissions/create',
                'slug' => 'permissions-create',
                'ref_id' => 10,
                'order_number'=> 11,
                'status' => 1,
                'created_by' => 1,
            ],
            [
                'navigation_id' => 1,
                'permission_id' => 12,
                'name' => 'Edit Permission',
                'icon' => 'Pencil',
                'url' => '/permissions/{id}/edit',
                'slug' => 'permissions-edit',
                'ref_id' => 10,
                'order_number'=> 12,
                'status' => 1,
                'created_by' => 1,
            ],
            [
                'navigation_id' => 1,
                'permission_id' => 13,
                'name' => 'Delete Permission',
                'icon' => 'Trash2',
                'url' => '/permissions/destroy',
                'slug' => 'permissions-destroy',
                'ref_id' => 10,
                'order_number'=> 13,
                'status' => 1,
                'created_by' => 1,
            ],
        ];

        try {
            foreach ($modules as $module) {
                NavigationModule::firstOrCreate(
                    [
                        'navigation_id' => $module['navigation_id'],
                        'name' => $module['name'],
                    ],
                    $module
                );
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
