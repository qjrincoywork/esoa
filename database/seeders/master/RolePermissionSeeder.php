<?php

namespace Database\Seeders\Master;

use App\Services\RoutePermissionService;
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
            'group_account_admin',
            'team_leader',
            'user',
            'broker',
            'account_branch_admin',
            'billing_admin',
        ];

        $permissions = [
            'dashboard',

            // users
            'users.index',
            'users.create',
            'users.bulk_create',
            'users.bulk_store',
            'users.edit',
            'users.destroy',
            'users.verify',
            'users.toggle_active',
            'users.account_mapping',
            'users.edit_permissions',
            'users.all_permissions',
            'users.update_permissions',
            'users.bulk_update_permissions',

            // activity logs (superadmin only — granted via syncPermissions below)
            'activity_logs.index',
            'activity_logs.show',
            'activity_logs.batch_siblings',

            // unmapped accounts & branches (superadmin only — granted via syncPermissions below)
            'unmapped_accounts.index',
            'unmapped_accounts.details',
            'unmapped_accounts.members',
            'unmapped_accounts.branches',
            'unmapped_accounts.mapped_users',

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
            'soas.batch_create',
            'soas.batch_store',
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

        // Every guarded route not listed above still gets its permission, so a fresh
        // install is never missing one the route files require.
        app(RoutePermissionService::class)->sync();

        Role::findByName('superadmin')->syncPermissions(Permission::all());

        $soaPermissions = Permission::whereIn('name', [
            'soas.index',
            'soas.create',
            'soas.edit',
            'soas.manage_file',
            'soas.find_member',
            'soas.member_files',
        ])->get();

        foreach (['broker', 'account_branch_admin'] as $roleName) {
            Role::findByName($roleName)->syncPermissions($soaPermissions);
        }

        $billingAdminPermissions = Permission::whereIn('name', [
            'soas.index',
            'soas.edit',
            'soas.manage_file',
        ])->get();

        Role::findByName('billing_admin')->syncPermissions($billingAdminPermissions);
    }
}
