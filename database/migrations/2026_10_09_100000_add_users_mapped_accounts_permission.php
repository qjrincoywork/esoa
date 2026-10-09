<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * The mapping tab's paged list of saved mappings. Route names double as permission
     * names ({@see \App\Http\Middleware\CheckPermission}).
     */
    private const PERMISSION = 'users.mapped_accounts';

    /** The tab whose audience the list inherits: whoever can open the tab can list it. */
    private const MIRRORS = 'users.account_mapping';

    /**
     * Register the permission and grant it to whoever can already open the mapping tab —
     * every role holding it and every user granted it directly. Until now the mappings
     * rode on the tab's own endpoint, so without this the tab would open with an empty
     * "Mapped" panel for everyone but superadmins.
     */
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => self::PERMISSION, 'guard_name' => 'web']);

        $mirrored = Permission::query()
            ->where('name', self::MIRRORS)
            ->where('guard_name', 'web')
            ->first();

        if ($mirrored) {
            foreach ($mirrored->roles as $role) {
                $role->givePermissionTo($permission);
            }

            User::query()
                ->whereHas('permissions', fn ($q) => $q->whereKey($mirrored->getKey()))
                ->get()
                ->each(fn (User $user) => $user->givePermissionTo($permission));
        }

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
