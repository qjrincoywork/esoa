<?php

namespace App\Services;

use App\Http\Middleware\CheckPermission;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Keeps the permissions table in step with the routes that require one.
 *
 * Route names double as permission names: {@see CheckPermission} authorizes every
 * request it guards against the permission named after the route. So every named route
 * behind that middleware needs a permission row, or it can neither be granted to anyone
 * (it never appears in a permission picker) nor reached by anyone but a superadmin.
 * Registering them by hand in migrations kept falling behind the route files; the routes
 * are the source of truth, so this reads them.
 *
 * Only adds, never removes. A permission with no matching route is reported, not
 * deleted — some are referenced by navigation modules or granted to roles on purpose.
 */
class RoutePermissionService
{
    public function __construct(
        private readonly Router $router,
        private readonly HttpKernel $kernel,
    ) {
    }

    /**
     * Names of every route guarded by the permission middleware, sorted.
     *
     * @return Collection<int, string>
     */
    public function routePermissionNames(): Collection
    {
        $markers = $this->middlewareMarkers();

        return collect($this->router->getRoutes()->getRoutes())
            ->filter(fn ($route) => $route->getName() !== null
                && collect($route->gatherMiddleware())
                    ->contains(fn ($middleware) => is_string($middleware)
                        && in_array(Str::before($middleware, ':'), $markers, true)))
            ->map(fn ($route) => $route->getName())
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * Guarded route names that have no permission row yet.
     *
     * @return Collection<int, string>
     */
    public function missing(): Collection
    {
        return $this->routePermissionNames()->diff($this->existingNames())->values();
    }

    /**
     * Permissions that no guarded route is named after — reported for review only.
     *
     * @return Collection<int, string>
     */
    public function orphans(): Collection
    {
        return $this->existingNames()->diff($this->routePermissionNames())->sort()->values();
    }

    /**
     * Create the missing permissions, ungranted, and flush Spatie's cache once.
     *
     * New permissions reach nobody until granted, except superadmin, who bypasses the
     * check anyway — so syncing never widens anyone's access by itself.
     *
     * @return Collection<int, string> The names that were created.
     */
    public function sync(): Collection
    {
        if (!Schema::hasTable(config('permission.table_names.permissions'))) {
            return collect();
        }

        $missing = $this->missing();

        foreach ($missing as $name) {
            Permission::create(['name' => $name, 'guard_name' => $this->guard()]);
        }

        if ($missing->isNotEmpty()) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return $missing;
    }

    /**
     * @return Collection<int, string>
     */
    private function existingNames(): Collection
    {
        return Permission::query()->where('guard_name', $this->guard())->pluck('name');
    }

    /**
     * Every name the permission middleware can be attached under: its aliases plus
     * the class itself, so a route that references it either way is recognised.
     *
     * Aliases are read from the HTTP kernel rather than the router: the router only
     * receives them when the kernel handles a request, so from the console (artisan,
     * the post-migrate hook) it would know none and every route would look unguarded.
     *
     * @return list<string>
     */
    private function middlewareMarkers(): array
    {
        $aliases = array_keys(array_filter(
            $this->kernel->getMiddlewareAliases(),
            fn ($class) => $class === CheckPermission::class,
        ));

        return [...$aliases, CheckPermission::class];
    }

    private function guard(): string
    {
        return config('auth.defaults.guard', 'web');
    }
}
