<?php

namespace App\Support;

use App\Enums\UserType;
use App\Models\User;

/**
 * Where an authenticated user should land: after login, or after being turned
 * away from a route they may not open.
 *
 * Candidates are tried in order and the first one the user may actually open wins,
 * so a redirect never points at another forbidden page (and never loops back to the
 * route that just rejected them). `home.index` is open to every authenticated user
 * and is the final fallback.
 */
final class LandingRoute
{
    /** Final fallback — reachable by any authenticated user. */
    private const FALLBACK = 'home.index';

    /**
     * User types that may never open the admin dashboard, whatever their role.
     * Keep in sync with the `deny_user_type` middleware on the `dashboard` route.
     */
    public const DASHBOARD_DENIED_TYPES = ['mappable'];

    /**
     * Resolve the landing route name for the user.
     *
     * @param string|null $except Route to skip, typically the one that just denied access.
     */
    public static function for(?User $user, ?string $except = null): string
    {
        if (!$user) {
            return 'login';
        }

        foreach (self::candidates($user) as $route => $allowed) {
            if ($route !== $except && $allowed()) {
                return $route;
            }
        }

        return self::FALLBACK;
    }

    /**
     * Resolve the landing URL for the user.
     */
    public static function urlFor(?User $user, ?string $except = null): string
    {
        return route(self::for($user, $except));
    }

    /**
     * Ordered candidate routes with their access checks, mirroring each route's
     * middleware. Checks are lazy so only the ones reached are evaluated.
     *
     * @return array<string, \Closure(): bool>
     */
    private static function candidates(User $user): array
    {
        return [
            // check_permissions; superadmin passes via the Gate::before hook
            'soas.dashboard' => fn (): bool => $user->can('soas.dashboard'),
            // role:superadmin + deny_user_type
            'dashboard' => fn (): bool => $user->hasRole('superadmin')
                && !$user->hasUserType(UserType::resolve(self::DASHBOARD_DENIED_TYPES)),
        ];
    }
}
