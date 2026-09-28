<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * The directory pane's Branches and Mapped Users tabs. Route names double as
     * permission names ({@see \App\Http\Middleware\CheckPermission}); these two endpoints
     * shipped without rows, which only went unnoticed while the module was superadmin-only.
     */
    private const PERMISSIONS = [
        'unmapped_accounts.branches',
        'unmapped_accounts.mapped_users',
    ];

    /** The listing whose audience the pane tabs inherit. */
    private const MIRRORS = 'unmapped_accounts.index';

    /**
     * Register the pane-tab permissions and grant them to whoever can already open the
     * listing — every role holding it and every user granted it directly — so the module
     * stays usable as a whole for anyone it has been given to. Where the listing
     * permission does not exist yet, they are created ungranted.
     */
    public function up(): void
    {
        $permissions = collect(self::PERMISSIONS)
            ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));

        $mirrored = Permission::query()
            ->where('name', self::MIRRORS)
            ->where('guard_name', 'web')
            ->first();

        if ($mirrored) {
            foreach ($mirrored->roles as $role) {
                $role->givePermissionTo($permissions);
            }

            User::query()
                ->whereHas('permissions', fn ($q) => $q->whereKey($mirrored->getKey()))
                ->get()
                ->each(fn (User $user) => $user->givePermissionTo($permissions));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Remove the permissions again, detaching them from every role and user.
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
