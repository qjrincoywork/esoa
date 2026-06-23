<?php

namespace Database\Seeders\Master;

use Illuminate\Database\Seeder;
use App\Models\Navigation;
use App\Models\NavigationModule;
use Spatie\Permission\Models\Permission;

class NavigationModuleSeeder extends Seeder
{
    public function run(): void
    {
        $navMap  = Navigation::pluck('id', 'name');
        $permMap = Permission::pluck('id', 'name');

        /*
         * 'perm'   — permission name used to resolve permission_id at seed time
         * 'parent' — slug of the parent NavigationModule (null = top-level)
         *
         * Note: roles.edit_permissions / users.edit_roles live under the Soa nav
         * but reference parent slugs from the ICT Admin nav.
         */
        $modules = [

            // ── ICT Admin ──────────────────────────────────────────────────────────

            ['nav' => 'ICT Admin', 'perm' => 'dashboard',             'name' => 'Admin Dashboard',              'slug' => 'dashboard',                    'icon' => 'SquareTerminal', 'color' => null,     'url' => '/dashboard',                   'parent' => null,                    'order' => 1],

            // Users
            ['nav' => 'ICT Admin', 'perm' => 'users.index',           'name' => 'Users',                        'slug' => 'users.index',                  'icon' => 'Users',          'color' => null,     'url' => '/users',                       'parent' => null,                    'order' => 2],
            ['nav' => 'ICT Admin', 'perm' => 'users.create',          'name' => 'Create User',                  'slug' => 'users.create',                 'icon' => 'UserPlus',       'color' => null,     'url' => '/users/create',                'parent' => 'users.index',           'order' => 3],
            ['nav' => 'ICT Admin', 'perm' => 'users.edit',            'name' => 'Edit User',                    'slug' => 'users.edit',                   'icon' => 'Pencil',         'color' => 'blue',   'url' => '/users/{id}/edit',             'parent' => 'users.index',           'order' => 4],
            ['nav' => 'ICT Admin', 'perm' => 'users.destroy',         'name' => 'Delete User',                  'slug' => 'users.destroy',                'icon' => 'Trash2',         'color' => 'red',    'url' => '/users/destroy',               'parent' => 'users.index',           'order' => 5],
            ['nav' => 'ICT Admin', 'perm' => 'users.verify',          'name' => 'Verify User',                  'slug' => 'users.verify',                 'icon' => 'ShieldCheck',    'color' => 'teal',   'url' => '/users/verify',                'parent' => 'users.index',           'order' => 1],
            ['nav' => 'ICT Admin', 'perm' => 'users.toggle_active',   'name' => 'Toggle User Active Status',    'slug' => 'users.toggle_active',          'icon' => 'ToggleRight',    'color' => 'orange', 'url' => '/users/toggle_active',         'parent' => 'users.index',           'order' => 6],

            // Roles
            ['nav' => 'ICT Admin', 'perm' => 'roles.index',           'name' => 'Roles',                        'slug' => 'roles.index',                  'icon' => 'Lock',           'color' => null,     'url' => '/roles',                       'parent' => null,                    'order' => 6],
            ['nav' => 'ICT Admin', 'perm' => 'roles.create',          'name' => 'Create Role',                  'slug' => 'roles.create',                 'icon' => 'UserPlus',       'color' => null,     'url' => '/roles/create',                'parent' => 'roles.index',           'order' => 7],
            ['nav' => 'ICT Admin', 'perm' => 'roles.edit',            'name' => 'Edit Role',                    'slug' => 'roles.edit',                   'icon' => 'Pencil',         'color' => 'blue',   'url' => '/roles/{id}/edit',             'parent' => 'roles.index',           'order' => 8],
            ['nav' => 'ICT Admin', 'perm' => 'roles.destroy',         'name' => 'Delete Role',                  'slug' => 'roles.destroy',                'icon' => 'Trash2',         'color' => 'red',    'url' => '/roles/destroy',               'parent' => 'roles.index',           'order' => 9],

            // Permissions
            ['nav' => 'ICT Admin', 'perm' => 'permissions.index',     'name' => 'Permissions',                  'slug' => 'permissions.index',            'icon' => 'Key',            'color' => null,     'url' => '/permissions',                 'parent' => null,                    'order' => 10],
            ['nav' => 'ICT Admin', 'perm' => 'permissions.create',    'name' => 'Create Permission',            'slug' => 'permissions.create',           'icon' => 'UserPlus',       'color' => null,     'url' => '/permissions/create',          'parent' => 'permissions.index',     'order' => 11],
            ['nav' => 'ICT Admin', 'perm' => 'permissions.edit',      'name' => 'Edit Permission',              'slug' => 'permissions.edit',             'icon' => 'Pencil',         'color' => 'blue',   'url' => '/permissions/{id}/edit',       'parent' => 'permissions.index',     'order' => 12],
            ['nav' => 'ICT Admin', 'perm' => 'permissions.destroy',   'name' => 'Delete Permission',            'slug' => 'permissions.destroy',          'icon' => 'Trash2',         'color' => 'red',    'url' => '/permissions/destroy',         'parent' => 'permissions.index',     'order' => 13],

            // Navigations
            ['nav' => 'ICT Admin', 'perm' => 'navigations.index',     'name' => 'Navigations',                  'slug' => 'navigations.index',            'icon' => 'Menu',           'color' => null,     'url' => '/navigations',                 'parent' => null,                    'order' => 15],
            ['nav' => 'ICT Admin', 'perm' => 'navigations.create',    'name' => 'Create Navigation',            'slug' => 'navigations.create',           'icon' => 'UserPlus',       'color' => null,     'url' => '/navigations/create',          'parent' => 'navigations.index',     'order' => 15],
            ['nav' => 'ICT Admin', 'perm' => 'navigations.edit',      'name' => 'Edit Navigation',              'slug' => 'navigations.edit',             'icon' => 'Pencil',         'color' => 'blue',   'url' => '/navigations/{id}/edit',       'parent' => 'navigations.index',     'order' => 16],
            ['nav' => 'ICT Admin', 'perm' => 'navigations.destroy',   'name' => 'Delete Navigation',            'slug' => 'navigations.destroy',          'icon' => 'Trash2',         'color' => 'red',    'url' => '/navigations/destroy',         'parent' => 'navigations.index',     'order' => 17],

            ['nav' => 'ICT Admin', 'perm' => 'navigation_modules.index',   'name' => 'Navigation Modules',           'slug' => 'navigation_modules.index',     'icon' => 'SquareMenu',     'color' => null,     'url' => '/navigation_modules',              'parent' => null,                      'order' => 16],
            ['nav' => 'ICT Admin', 'perm' => 'navigation_modules.edit',    'name' => 'Edit Navigation Module',       'slug' => 'navigation_modules.edit',      'icon' => 'Pencil',         'color' => 'blue',   'url' => '/navigation_modules/{id}/edit',    'parent' => 'navigation_modules.index', 'order' => 1],
            ['nav' => 'ICT Admin', 'perm' => 'navigation_modules.destroy', 'name' => 'Delete Navigation Module',     'slug' => 'navigation_modules.destroy',   'icon' => 'Trash2',         'color' => 'red',    'url' => '/navigation_modules/destroy',      'parent' => 'navigation_modules.index', 'order' => 2],

            // ── SOA ───────────────────────────────────────────────────────────────

            // Top-level SOA entries
            ['nav' => 'Soa', 'perm' => 'soas.dashboard',              'name' => 'Dashboard',                    'slug' => 'soas.dashboard',               'icon' => 'House',          'color' => null,     'url' => '/soas/dashboard',              'parent' => null,                    'order' => 18],
            ['nav' => 'Soa', 'perm' => 'soas.index',                  'name' => 'Soa List',                     'slug' => 'soas.index',                   'icon' => 'Menu',           'color' => null,     'url' => '/soas',                        'parent' => null,                    'order' => 18],
            ['nav' => 'Soa', 'perm' => 'soas.list',                   'name' => 'Billing Invoices',             'slug' => 'soas.list',                    'icon' => 'ReceiptText',    'color' => null,     'url' => '/soas/list',                   'parent' => null,                    'order' => 19],
            ['nav' => 'Soa', 'perm' => 'concerns.index',              'name' => 'Concerns',                     'slug' => 'concerns.index',               'icon' => 'Tickets',        'color' => null,     'url' => '/concerns',                    'parent' => null,                    'order' => 21],
            ['nav' => 'Soa', 'perm' => 'account_payments.index',      'name' => 'Remittance Advices',           'slug' => 'account_payments.index',       'icon' => 'HandCoins',      'color' => null,     'url' => '/account_payments',            'parent' => null,                    'order' => 22],
            ['nav' => 'Soa', 'perm' => 'soas.find_member',            'name' => 'Find Member',                  'slug' => 'soas.find_member',             'icon' => 'Search',         'color' => null,     'url' => '/soas/find_member',            'parent' => null,                    'order' => 23],

            // SOA sub-actions — cross-nav: in Soa nav but parented under ICT Admin modules
            ['nav' => 'Soa', 'perm' => 'roles.edit_permissions',      'name' => 'Manage Permissions',           'slug' => 'roles.edit_permissions',       'icon' => 'Key',            'color' => 'green',  'url' => '/roles/{id}/edit_permissions', 'parent' => 'roles.index',           'order' => 1],
            ['nav' => 'Soa', 'perm' => 'users.edit_roles',            'name' => 'Manage Roles',                 'slug' => 'users.edit_roles',             'icon' => 'UserRoundCog',   'color' => 'purple', 'url' => '/users/{id}/edit_roles',       'parent' => 'users.index',           'order' => 2],

            // SOA — under soas.index
            ['nav' => 'Soa', 'perm' => 'soas.show',                   'name' => 'View Soa',                     'slug' => 'soas.show',                    'icon' => 'Eye',            'color' => 'green',  'url' => '/soas/{id}/show',              'parent' => 'soas.index',            'order' => 1],
            ['nav' => 'Soa', 'perm' => 'soas.manage_file',             'name' => 'Undo Soa Tag',                 'slug' => 'soas.untag',                   'icon' => 'Undo',           'color' => 'blue',   'url' => '/soas/{id}/untag',             'parent' => 'soas.index',            'order' => 4],
            ['nav' => 'Soa', 'perm' => 'soas.destroy',                'name' => 'Delete Soa',                   'slug' => 'soas.destroy',                 'icon' => 'Trash2',         'color' => 'red',    'url' => '/soas/destroy',                'parent' => 'soas.index',            'order' => 5],
            ['nav' => 'Soa', 'perm' => 'soas.create',                 'name' => 'Create Soa',                   'slug' => 'soas.create',                  'icon' => 'FilePlusCorner', 'color' => 'green',  'url' => '/soas/create',                 'parent' => 'soas.index',            'order' => 7],

            // SOA — under soas.list (Billing Invoices)
            ['nav' => 'Soa', 'perm' => 'soas.edit',                   'name' => 'Edit Soa',                     'slug' => 'soas.edit',                    'icon' => 'Pencil',         'color' => 'blue',   'url' => '/soas/{id}/edit',              'parent' => 'soas.list',             'order' => 2],
            ['nav' => 'Soa', 'perm' => 'soas.file_list',              'name' => 'Billing PDF File',             'slug' => 'soas.billing_attachments',     'icon' => 'FileText',       'color' => 'yellow', 'url' => '/soas/{id}/attachment/{type}', 'parent' => 'soas.list',             'order' => 20],
            ['nav' => 'Soa', 'perm' => 'soas.file_list',              'name' => 'Records Management File List', 'slug' => 'soas.file_list',               'icon' => 'Files',          'color' => 'blue',   'url' => '/soas/file_list',              'parent' => 'soas.list',             'order' => 21],

            // SOA — under concerns.index (Concerns)
            ['nav' => 'Soa', 'perm' => 'soas.manage_file',            'name' => 'Manage Soa File',              'slug' => 'soas.manage_file',             'icon' => 'File',           'color' => 'yellow', 'url' => '/soas/{id}/manage_file',       'parent' => 'concerns.index',        'order' => 3],
            ['nav' => 'Soa', 'perm' => 'concerns.edit',               'name' => 'Edit Concern',                 'slug' => 'concerns.edit',                'icon' => 'Pencil',         'color' => 'blue',   'url' => '/concerns/{id}/edit',          'parent' => 'concerns.index',        'order' => 2],
            ['nav' => 'Soa', 'perm' => 'concerns.preview_file',       'name' => 'Concern File',                 'slug' => 'concerns.preview_file',        'icon' => 'FileText',       'color' => 'green',  'url' => '/concerns/preview_file',       'parent' => 'concerns.index',        'order' => 3],
            ['nav' => 'Soa', 'perm' => 'concerns.destroy',            'name' => 'Delete Concern',               'slug' => 'concerns.destroy',             'icon' => 'Trash2',         'color' => 'red',    'url' => '/concerns/destroy',            'parent' => 'concerns.index',        'order' => 5],
            ['nav' => 'Soa', 'perm' => 'concerns.create',             'name' => 'Create Concern',               'slug' => 'concerns.create',              'icon' => 'FilePlusCorner', 'color' => 'green',  'url' => '/concerns/create',             'parent' => 'concerns.index',        'order' => 7],

            // SOA — under account_payments.index (Remittance Advices)
            ['nav' => 'Soa', 'perm' => 'account_payments.edit',       'name' => 'Edit Remittance Advice',       'slug' => 'account_payments.edit',        'icon' => 'Pencil',         'color' => 'blue',   'url' => '/account_payments/{id}/edit',  'parent' => 'account_payments.index', 'order' => 2],
            ['nav' => 'Soa', 'perm' => 'account_payments.preview_file','name' => 'Remittance Advice File',      'slug' => 'account_payments.preview_file','icon' => 'FileText',       'color' => 'green',  'url' => '/account_payments/preview_file','parent' => 'account_payments.index','order' => 3],
            ['nav' => 'Soa', 'perm' => 'account_payments.create',     'name' => 'Upload Remittance Advice',     'slug' => 'account_payments.create',      'icon' => 'FilePlusCorner', 'color' => 'green',  'url' => '/account_payments/create',     'parent' => 'account_payments.index', 'order' => 4],
            ['nav' => 'Soa', 'perm' => 'account_payments.destroy',    'name' => 'Delete Remittance Advice',     'slug' => 'account_payments.destroy',     'icon' => 'Trash2',         'color' => 'red',    'url' => '/account_payments/destroy',    'parent' => 'account_payments.index', 'order' => 5],

            // SOA — under soas.find_member
            ['nav' => 'Soa', 'perm' => 'soas.member_files',           'name' => 'Member Files',                 'slug' => 'soas.member_files',            'icon' => 'FolderOpen',     'color' => 'blue',   'url' => '/soas/member_files',           'parent' => 'soas.find_member',      'order' => 24],
        ];

        // First pass: seed top-level modules (parent = null)
        foreach (array_filter($modules, fn($m) => $m['parent'] === null) as $module) {
            NavigationModule::firstOrCreate(
                ['slug' => $module['slug']],
                $this->buildAttributes($module, $navMap, $permMap, null)
            );
        }

        // Build slug → id map after parents exist so children can resolve ref_id
        $slugMap = NavigationModule::pluck('id', 'slug');

        // Second pass: seed child modules
        foreach (array_filter($modules, fn($m) => $m['parent'] !== null) as $module) {
            NavigationModule::firstOrCreate(
                ['slug' => $module['slug']],
                $this->buildAttributes($module, $navMap, $permMap, $slugMap[$module['parent']] ?? null)
            );
        }
    }

    private function buildAttributes(array $module, $navMap, $permMap, ?int $refId): array
    {
        return [
            'navigation_id' => $navMap[$module['nav']] ?? null,
            'permission_id' => $permMap[$module['perm']] ?? null,
            'name'          => $module['name'],
            'slug'          => $module['slug'],
            'icon'          => $module['icon'],
            'color'         => $module['color'],
            'url'           => $module['url'],
            'ref_id'        => $refId,
            'order_number'  => $module['order'],
            'status'        => 1,
            'created_by'    => 1,
        ];
    }
}
