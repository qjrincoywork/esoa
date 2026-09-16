<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** Permission (and route) name of the legacy remarks endpoint. */
    private const PERMISSION = 'soas.old_remarks';

    /** The endpoint whose audience the new one inherits. */
    private const MIRRORS = 'soas.concerns';

    /**
     * Grant the legacy remarks endpoint to whoever already sees the concerns pane.
     *
     * Route names double as permission names ({@see \App\Http\Middleware\CheckPermission}),
     * so the "Old Remarks / Concerns" sub-tab needs its own permission row or every
     * non-superadmin request to it answers 403. The roles are read from the concerns
     * permission the sub-tab lives under rather than hardcoded, because permissions are
     * maintained in the database rather than in the seeders; on a fresh install where
     * that row does not exist yet, the permission is created ungranted (superadmins
     * bypass the check regardless).
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
