<?php

namespace App\Http\Requests\Concerns;

/**
 * Authorize a form request against the permission named after its route.
 *
 * Route names double as permission names ({@see \App\Http\Middleware\CheckPermission}),
 * so the request states its own audience without naming a role: anyone granted the
 * permission — through a role or directly — passes, and superadmin passes through the
 * `Gate::before` bypass in {@see \App\Providers\AuthServiceProvider}. This keeps the
 * check where the route is mounted from mattering, while leaving who may call it to
 * whatever permissions have been granted.
 */
trait AuthorizesRoutePermission
{
    /**
     * Allow the request only when the user holds the current route's permission.
     *
     * A route without a name has no permission to check against and is refused.
     */
    public function authorize(): bool
    {
        $permission = $this->route()?->getName();

        return $permission !== null && ($this->user()?->can($permission) ?? false);
    }
}
