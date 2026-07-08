<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce two-factor authentication for privileged roles (F-11).
 *
 * Fortify makes 2FA available but optional. For roles listed in
 * config('vc.enforce_2fa_roles') this middleware blocks the application until
 * the user has confirmed 2FA (two_factor_confirmed_at), redirecting them to the
 * setup page. The 2FA setup/confirm endpoints and logout are allow-listed so
 * the user is never locked out of the enrolment flow itself.
 */
class EnsureTwoFactorEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $this->mustUseTwoFactor($user) && is_null($user->two_factor_confirmed_at)) {
            if (! $this->onAllowedRoute($request)) {
                if ($request->header('X-Inertia')) {
                    return Inertia::location(route('two-factor.show'));
                }

                if (! $request->expectsJson()) {
                    return redirect()->route('two-factor.show')->with(
                        'status',
                        'Two-factor authentication is required for your role. Please enable it to continue.'
                    );
                }

                abort(Response::HTTP_FORBIDDEN, 'Two-factor authentication is required.');
            }
        }

        return $next($request);
    }

    /**
     * Whether the given user's role requires 2FA.
     */
    private function mustUseTwoFactor($user): bool
    {
        $roles = (array) config('vc.enforce_2fa_roles', []);

        return $roles !== [] && $user->hasAnyRole($roles);
    }

    /**
     * Routes that must stay reachable so the user can enrol in / manage 2FA
     * and log out, even before 2FA is confirmed.
     */
    private function onAllowedRoute(Request $request): bool
    {
        $allowedNames = [
            'two-factor.show',
            'profile.edit',
            'logout',
        ];

        if (in_array($request->route()?->getName(), $allowedNames, true)) {
            return true;
        }

        // Fortify's two-factor + confirm-password + logout endpoints are not
        // all named, so match their URIs directly.
        return $request->is(
            'settings/two-factor',
            'user/two-factor-authentication',
            'user/two-factor-authentication/*',
            'user/confirmed-two-factor-authentication',
            'user/two-factor-qr-code',
            'user/two-factor-secret-key',
            'user/two-factor-recovery-codes',
            'user/confirm-password',
            'user/confirmed-password-status',
            'logout',
        );
    }
}
