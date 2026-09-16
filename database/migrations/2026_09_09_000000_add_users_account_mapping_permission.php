<?php

use App\Models\NavigationModule;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** Permission (and route) name of the account/branch mapping screen. */
    private const PERMISSION = 'users.account_mapping';

    /** The user action whose audience the new one inherits. */
    private const MIRRORS = 'users.edit';

    /** Slug of the module the action hangs off, i.e. the row it acts on. */
    private const PARENT_SLUG = 'users.index';

    /**
     * Register the account/branch mapping action.
     *
     * Two rows are needed, and for different reasons. Route names double as permission
     * names ({@see \App\Http\Middleware\CheckPermission}), so the endpoint needs a
     * permission or every non-superadmin request to it answers 403; and the users table
     * builds its action column from the navigation sub-modules of the current page, so
     * the action needs a module row under `users.index` or the button never renders.
     *
     * The audience is read from the edit permission rather than hardcoded, because
     * permissions are maintained in the database rather than in the seeders; on a fresh
     * install where that row does not exist yet the permission is created ungranted
     * (superadmins bypass the check regardless).
     */
    public function up(): void
    {
        $permission = Permission::firstOrCreate([
            'name' => self::PERMISSION,
            'guard_name' => 'web',
        ]);

        $mirrored = Permission::query()
            ->where('name', self::MIRRORS)
            ->where('guard_name', 'web')
            ->first();

        $roles = $mirrored?->roles->pluck('name')->all() ?? [];

        if ($roles !== []) {
            $permission->syncRoles($roles);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $parent = NavigationModule::query()
            ->where('slug', self::PARENT_SLUG)
            ->first();

        if (!$parent) {
            return;
        }

        NavigationModule::firstOrCreate(
            ['slug' => self::PERMISSION],
            [
                'navigation_id' => $parent->navigation_id,
                'permission_id' => $permission->id,
                'name' => 'Map Accounts & Branches',
                'slug' => self::PERMISSION,
                'icon' => 'Network',
                'color' => 'indigo',
                'url' => '/users/{id}/account_mapping',
                'ref_id' => $parent->id,
                'order_number' => 7,
                'status' => 1,
                'created_by' => 1,
            ]
        );
    }

    /**
     * Remove the action again: drop its module row, then the permission, detaching it
     * from every role that held it.
     */
    public function down(): void
    {
        NavigationModule::query()
            ->where('slug', self::PERMISSION)
            ->forceDelete();

        Permission::query()
            ->where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
