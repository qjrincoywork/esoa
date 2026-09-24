<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Permission (and route) names of the batch billing-invoice upload.
     *
     * Two endpoints, so two permissions: the pane fetches its template metadata from
     * one and posts the file to the other, and route names double as permission names
     * ({@see \App\Http\Middleware\CheckPermission}).
     *
     * @var list<string>
     */
    private const PERMISSIONS = [
        'soas.batch_create',
        'soas.batch_store',
    ];

    /** The upload action whose audience the batch upload inherits. */
    private const MIRRORS = 'soas.store';

    /**
     * Register the batch-upload permissions.
     *
     * No navigation module row is created: the button lives in the list's toolbar and
     * is gated on the permission itself, the way Export is, whereas a module row under
     * `soas.list` would also add a per-row action button to the table.
     *
     * The audience is read from the single-upload permission rather than hardcoded,
     * because permissions are maintained in the database rather than in the seeders; on
     * a fresh install where that row does not exist yet the permissions are created
     * ungranted (superadmins bypass the check regardless).
     */
    public function up(): void
    {
        $mirrored = Permission::query()
            ->where('name', self::MIRRORS)
            ->where('guard_name', 'web')
            ->first();

        $roles = $mirrored?->roles->pluck('name')->all() ?? [];

        foreach (self::PERMISSIONS as $name) {
            $permission = Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);

            if ($roles !== []) {
                $permission->syncRoles($roles);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Remove the permissions again, detaching them from every role that held them.
     */
    public function down(): void
    {
        Permission::query()
            ->whereIn('name', self::PERMISSIONS)
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
