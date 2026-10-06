<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * The user pane's Activity tab. Route names double as permission names
     * ({@see \App\Http\Middleware\CheckPermission}), and the pane offers the tab only to
     * whoever holds it.
     */
    private const PERMISSION = 'users.activity_logs';

    /**
     * Register the permission and grant it to superadmin alone — the same audience as
     * the audit trail it is a slice of ({@see \App\Http\Requests\ActivityLog\ListRequest}).
     * Superadmin bypasses the route check anyway; the grant is what makes the tab show,
     * since the pane reads the shared permission list.
     */
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => self::PERMISSION, 'guard_name' => 'web']);

        Role::query()
            ->where('name', 'superadmin')
            ->where('guard_name', 'web')
            ->first()
            ?->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Remove the permission again, detaching it from every role and user.
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
