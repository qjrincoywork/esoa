<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * The user pane's "User Details" tab, now fetched on its own when opened. Route names
     * double as permission names ({@see \App\Http\Middleware\CheckPermission}).
     */
    private const PERMISSION = 'users.details';

    /** The listing whose audience the pane inherits: whoever can list users can open one. */
    private const MIRRORS = 'users.index';

    /**
     * Register the permission and grant it to whoever can already open the users list —
     * every role holding it and every user granted it directly — so the pane keeps
     * working for everyone it worked for. Until now the details rode on the mapping
     * tab's endpoint, so a role without the mapping permission only ever saw the list
     * row's fields; with its own permission, it sees the full details.
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
