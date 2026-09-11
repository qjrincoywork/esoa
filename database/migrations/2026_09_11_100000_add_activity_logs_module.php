<?php

use App\Models\Navigation;
use App\Models\NavigationModule;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Permissions the audit-trail module needs — route names double as permission
     * names ({@see \App\Http\Middleware\CheckPermission}), so each endpoint needs one.
     */
    private const PERMISSIONS = [
        'activity_logs.index' => 'Activity Logs',
        'activity_logs.show' => 'View Activity Log Entry',
    ];

    /** The navigation the module is listed under. */
    private const NAVIGATION = 'ICT Admin';

    /**
     * Register the activity-log module: its permissions, and its sidebar entry.
     *
     * Granted to superadmin alone. That is the whole point of the module — it shows
     * who changed what across every audited module, including changes made by other
     * administrators — and the sidebar is permission-driven
     * ({@see \App\Models\Navigation::accessibleModules()}), so no one else is offered it.
     */
    public function up(): void
    {
        $permissions = collect(self::PERMISSIONS)
            ->mapWithKeys(fn (string $label, string $name): array => [
                $name => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']),
            ]);

        $superadmin = \Spatie\Permission\Models\Role::query()
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

        // Sits after the other ICT Admin entries; the trail is a place you go to look
        // something up, not part of the day-to-day flow above it.
        NavigationModule::firstOrCreate(
            ['slug' => 'activity_logs.index'],
            [
                'navigation_id' => $navigationId,
                'permission_id' => $permissions['activity_logs.index']->id,
                'name' => 'Activity Logs',
                'slug' => 'activity_logs.index',
                'icon' => 'ScrollText',
                'color' => null,
                'url' => '/activity_logs',
                'ref_id' => null,
                'order_number' => 20,
                'status' => 1,
                'created_by' => 1,
            ]
        );
    }

    /**
     * Remove the module again: its sidebar entry, then its permissions.
     */
    public function down(): void
    {
        NavigationModule::query()
            ->where('slug', 'activity_logs.index')
            ->forceDelete();

        Permission::query()
            ->whereIn('name', array_keys(self::PERMISSIONS))
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
