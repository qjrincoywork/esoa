<?php

use App\Models\NavigationModule;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Permission (and route) name of the "Email Notifications" action. One endpoint
     * serves the row action and the bulk action alike.
     */
    private const ACTION = 'users.email_notifications';

    /** The user action whose audience the new one inherits. */
    private const MIRRORS = 'users.toggle_active';

    /** Slug of the module the action hangs off, i.e. the row it acts on. */
    private const PARENT_SLUG = 'users.index';

    /**
     * Register the permission and the action-column module.
     *
     * Choosing which notifications a user is sent is the same trust level as
     * deactivating them, so the audience is read from the active-status permission rather
     * than hardcoded. Where that row does not exist yet the permission is created
     * ungranted (superadmins bypass the check regardless). The users table builds its
     * action column from the navigation sub-modules of the page, so the action needs a
     * module row under `users.index` or the button never renders.
     */
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => self::ACTION, 'guard_name' => 'web']);

        $roles = Permission::query()
            ->where('name', self::MIRRORS)
            ->where('guard_name', 'web')
            ->first()
            ?->roles ?? collect();

        foreach ($roles as $role) {
            $role->givePermissionTo($permission);
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
                'permission_id' => $permission->id,
                'name' => 'Email Notifications',
                'slug' => self::ACTION,
                'icon' => 'Mail',
                'color' => 'orange',
                'url' => '/users/email_notifications',
                'ref_id' => $parent->id,
                'order_number' => 8,
                'status' => 1,
                'created_by' => 1,
            ]
        );
    }

    /**
     * Remove the action again: drop its module row, then the permission, detaching it
     * from every role and user that held it.
     */
    public function down(): void
    {
        NavigationModule::query()
            ->where('slug', self::ACTION)
            ->forceDelete();

        Permission::query()
            ->where('name', self::ACTION)
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
