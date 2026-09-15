<?php

use App\Models\Navigation;
use App\Models\NavigationModule;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Permissions the unmapped-directory module needs — route names double as
     * permission names ({@see \App\Http\Middleware\CheckPermission}), so each endpoint
     * needs one.
     */
    private const PERMISSIONS = [
        'unmapped_accounts.index' => 'Unmapped Accounts',
    ];

    /** The navigation the module is listed under. */
    private const NAVIGATION = 'ICT Admin';

    /**
     * Register the unmapped-directory module: its permission, and its sidebar entry.
     *
     * Granted to superadmin alone. The listing enumerates every account and branch in
     * the client directory that nobody has been given — the whole HMS directory, minus
     * what is mapped — which is exactly the reach the account/branch pickers are
     * restricted to for everyone else. The sidebar is permission-driven
     * ({@see \App\Models\Navigation::accessibleModules()}), so no one else is offered it.
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

        $navigationId = Navigation::query()->where('name', self::NAVIGATION)->value('id');

        if (!$navigationId) {
            return;
        }

        // Sits next to the user administration it reports on, above the audit trail:
        // the gap it shows is acted on by mapping a user, which is right there.
        NavigationModule::firstOrCreate(
            ['slug' => 'unmapped_accounts.index'],
            [
                'navigation_id' => $navigationId,
                'permission_id' => $permissions['unmapped_accounts.index']->id,
                'name' => 'Unmapped Accounts',
                'slug' => 'unmapped_accounts.index',
                'icon' => 'Unlink',
                'color' => null,
                'url' => '/unmapped_accounts',
                'ref_id' => null,
                'order_number' => 19,
                'status' => 1,
                'created_by' => 1,
            ]
        );
    }

    /**
     * Remove the module again: its sidebar entry, then its permission.
     */
    public function down(): void
    {
        NavigationModule::query()
            ->where('slug', 'unmapped_accounts.index')
            ->forceDelete();

        Permission::query()
            ->whereIn('name', array_keys(self::PERMISSIONS))
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
