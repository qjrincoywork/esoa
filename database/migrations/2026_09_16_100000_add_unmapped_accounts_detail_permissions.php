<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * The endpoints behind the unmapped listing's detail pane. Route names double as
     * permission names ({@see \App\Http\Middleware\CheckPermission}), so each needs one.
     */
    private const PERMISSIONS = [
        'unmapped_accounts.details' => 'View Unmapped Account Details',
        'unmapped_accounts.members' => 'View Unmapped Account Members',
    ];

    /**
     * Register the detail-pane permissions, granted to superadmin alone.
     *
     * The same audience as the listing they are opened from: between them these read
     * any account in the client directory and the cardholders behind it. Superadmin
     * bypasses the permission check outright, so these rows change nothing today — they
     * exist so the module can be widened to another role later by granting them, rather
     * than by discovering the endpoints were never named.
     */
    public function up(): void
    {
        $permissions = collect(self::PERMISSIONS)
            ->mapWithKeys(fn (string $label, string $name): array => [
                $name => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']),
            ]);

        $superadmin = Role::query()
            ->where('name', 'superadmin')
            ->where('guard_name', 'web')
            ->first();

        if ($superadmin) {
            $superadmin->givePermissionTo($permissions->values()->all());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Remove the permissions again.
     */
    public function down(): void
    {
        Permission::query()
            ->whereIn('name', array_keys(self::PERMISSIONS))
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
