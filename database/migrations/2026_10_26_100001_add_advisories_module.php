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
     * Permissions the advisories module needs — route names double as permission names
     * ({@see \App\Http\Middleware\CheckPermission}), so each endpoint needs one. The
     * image endpoint is not here: every signed-in user reads it.
     */
    private const PERMISSIONS = [
        'advisories.index',
        'advisories.store',
        'advisories.edit',
        'advisories.update',
        'advisories.toggle_active',
        'advisories.destroy',
    ];

    /** The navigation the module is listed under. */
    private const NAVIGATION = 'ICT Admin';

    /** Slug of the sidebar entry; its row actions hang off it. */
    private const PARENT_SLUG = 'advisories.index';

    /**
     * Row actions of the listing, as sub-modules of the sidebar entry — the table builds
     * its action column from them, so an action without a row never renders.
     */
    private const ROW_ACTIONS = [
        ['slug' => 'advisories.edit', 'name' => 'Edit Advisory', 'icon' => 'Pencil', 'color' => 'blue', 'url' => '/advisories/{id}/edit', 'order_number' => 1],
        ['slug' => 'advisories.toggle_active', 'name' => 'Enable / Disable Advisory', 'icon' => 'ToggleRight', 'color' => 'orange', 'url' => '/advisories/toggle_active', 'order_number' => 2],
        ['slug' => 'advisories.destroy', 'name' => 'Delete Advisory', 'icon' => 'Trash2', 'color' => 'red', 'url' => '/advisories/destroy', 'order_number' => 3],
    ];

    /**
     * Register the advisories module: its permissions, its sidebar entry and its row actions.
     *
     * Granted to superadmin alone — an advisory pops up in front of every user of the
     * system, which is a system administrator's call. The permissions can be granted to
     * other roles from the Roles screen like any other.
     */
    public function up(): void
    {
        $permissions = collect(self::PERMISSIONS)
            ->mapWithKeys(fn (string $name): array => [
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

        // Sits with the other system-wide settings, just above the unmapped-directory report.
        $parent = NavigationModule::firstOrCreate(
            ['slug' => self::PARENT_SLUG],
            [
                'navigation_id' => $navigationId,
                'permission_id' => $permissions[self::PARENT_SLUG]->id,
                'name' => 'Advisories',
                'slug' => self::PARENT_SLUG,
                'icon' => 'Megaphone',
                'color' => null,
                'url' => '/advisories',
                'ref_id' => null,
                'order_number' => 18,
                'status' => 1,
                'created_by' => 1,
            ]
        );

        foreach (self::ROW_ACTIONS as $action) {
            NavigationModule::firstOrCreate(
                ['slug' => $action['slug']],
                [
                    ...$action,
                    'navigation_id' => $navigationId,
                    'permission_id' => $permissions[$action['slug']]->id,
                    'ref_id' => $parent->id,
                    'status' => 1,
                    'created_by' => 1,
                ]
            );
        }
    }

    /**
     * Remove the module again: its row actions and sidebar entry, then its permissions.
     */
    public function down(): void
    {
        NavigationModule::query()
            ->whereIn('slug', [self::PARENT_SLUG, ...array_column(self::ROW_ACTIONS, 'slug')])
            ->forceDelete();

        Permission::query()
            ->whereIn('name', self::PERMISSIONS)
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
