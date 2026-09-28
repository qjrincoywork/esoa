<?php

use App\Models\NavigationModule;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** Permission (and route) name of the per-row "Manage Permissions" action. */
    private const ACTION = 'users.edit_permissions';

    /**
     * Every endpoint behind the direct-permission modals. Route names double as
     * permission names ({@see \App\Http\Middleware\CheckPermission}), so each needs one.
     */
    private const PERMISSIONS = [
        self::ACTION,
        'users.all_permissions',
        'users.update_permissions',
        'users.bulk_update_permissions',
    ];

    /** The user action whose audience the new ones inherit. */
    private const MIRRORS = 'users.edit_roles';

    /** Slug of the module the action hangs off, i.e. the row it acts on. */
    private const PARENT_SLUG = 'users.index';

    /**
     * Register the direct-permission endpoints and the action-column module.
     *
     * Granting a permission straight to a user is the same trust level as assigning
     * them a role, so the audience is read from the role-management permission rather
     * than hardcoded — permissions are maintained in the database, not the seeders.
     * Where that row does not exist yet the permissions are created ungranted
     * (superadmins bypass the check regardless). The users table builds its action
     * column from the navigation sub-modules of the page, so the action needs a module
     * row under `users.index` or the button never renders.
     */
    public function up(): void
    {
        $permissions = collect(self::PERMISSIONS)
            ->mapWithKeys(fn (string $name): array => [
                $name => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']),
            ]);

        $roles = Permission::query()
            ->where('name', self::MIRRORS)
            ->where('guard_name', 'web')
            ->first()
            ?->roles ?? collect();

        foreach ($roles as $role) {
            $role->givePermissionTo($permissions->values()->all());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $parent = NavigationModule::query()
            ->where('slug', self::PARENT_SLUG)
            ->first();

        if (!$parent) {
            return;
        }

        NavigationModule::firstOrCreate(
            ['slug' => self::ACTION],
            [
                'navigation_id' => $parent->navigation_id,
                'permission_id' => $permissions[self::ACTION]->id,
                'name' => 'Manage Permissions',
                'slug' => self::ACTION,
                'icon' => 'KeyRound',
                'color' => 'green',
                'url' => '/users/{id}/edit_permissions',
                'ref_id' => $parent->id,
                'order_number' => 5,
                'status' => 1,
                'created_by' => 1,
            ]
        );
    }

    /**
     * Remove the action again: drop its module row, then the permissions, detaching
     * them from every role and user that held them.
     */
    public function down(): void
    {
        NavigationModule::query()
            ->where('slug', self::ACTION)
            ->forceDelete();

        Permission::query()
            ->whereIn('name', self::PERMISSIONS)
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
