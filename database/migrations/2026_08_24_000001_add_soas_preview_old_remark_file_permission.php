<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** Permission (and route) name of the legacy remark attachment stream. */
    private const PERMISSION = 'soas.preview_old_remark_file';

    /** The endpoint whose audience the new one inherits. */
    private const MIRRORS = 'soas.old_remarks';

    /**
     * Grant the legacy attachment stream to whoever can read the legacy thread.
     *
     * Route names double as permission names ({@see \App\Http\Middleware\CheckPermission}),
     * so serving those files needs its own permission row or every non-superadmin gets a
     * 403 when opening one. Anyone who can see the messages can see what was attached to
     * them, so the roles are read from the remarks permission rather than hardcoded.
     */
    public function up(): void
    {
        $permission = Permission::firstOrCreate([
            'name' => self::PERMISSION,
            'guard_name' => 'web',
        ]);

        $roles = Permission::query()
            ->where('name', self::MIRRORS)
            ->where('guard_name', 'web')
            ->first()
            ?->roles
            ->pluck('name')
            ->all() ?? [];

        if ($roles !== []) {
            $permission->syncRoles($roles);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Drop the permission again, detaching it from every role that held it.
     */
    public function down(): void
    {
        Permission::query()
            ->where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
