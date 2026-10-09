<?php

use App\Models\NavigationModule;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** Permission (and route) name of the "Assign users" action, and of its row module. */
    private const ACTION = 'unmapped_accounts.assign_users';

    /** The action's user picker, which it cannot work without. */
    private const PICKER = 'unmapped_accounts.assignable_users';

    /** The listing whose audience the action inherits. */
    private const MIRRORS = 'unmapped_accounts.index';

    /** Slug of the module the action hangs off, i.e. the listing its rows belong to. */
    private const PARENT_SLUG = 'unmapped_accounts.index';

    /**
     * Register the "Assign users" action on the unmapped listing.
     *
     * Two rows are needed, for different reasons. Route names double as permission names
     * ({@see \App\Http\Middleware\CheckPermission}), so both endpoints need a permission
     * or every non-superadmin request answers 403; and a listing builds its row actions
     * from the navigation sub-modules of the current page, so the action needs a module
     * row under `unmapped_accounts.index` or the button never renders.
     *
     * Granted to whoever can already open the listing — every role holding it and every
     * user granted it directly — which today is superadmin only. It writes user access,
     * so widening the listing to other roles later is a separate decision from handing
     * them this action: the permission can be withheld on its own.
     */
    public function up(): void
    {
        $permissions = collect([self::ACTION, self::PICKER])
            ->mapWithKeys(fn (string $name) => [$name => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'])]);

        $mirrored = Permission::query()
            ->where('name', self::MIRRORS)
            ->where('guard_name', 'web')
            ->first();

        if ($mirrored) {
            foreach ($mirrored->roles as $role) {
                $role->givePermissionTo($permissions->values());
            }

            User::query()
                ->whereHas('permissions', fn ($q) => $q->whereKey($mirrored->getKey()))
                ->get()
                ->each(fn (User $user) => $user->givePermissionTo($permissions->values()));
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
                'name' => 'Assign Users',
                'slug' => self::ACTION,
                'icon' => 'UserPlus',
                'color' => 'indigo',
                'url' => '/unmapped_accounts/assign_users',
                'ref_id' => $parent->id,
                'order_number' => 1,
                'status' => 1,
                'created_by' => 1,
            ]
        );
    }

    /**
     * Remove the action again: drop its module row, then both permissions, detaching
     * them from every role and user that held them.
     */
    public function down(): void
    {
        NavigationModule::query()
            ->where('slug', self::ACTION)
            ->forceDelete();

        Permission::query()
            ->whereIn('name', [self::ACTION, self::PICKER])
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
