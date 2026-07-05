<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

/**
 * Post-login redirect for the application.
 *
 * Single responsibility: decide where an authenticated user lands after logging in.
 * Users are sent to the SOA dashboard when they may access it, otherwise to the
 * generic dashboard — mirroring the SOA dashboard route's permission gate so a
 * non-permitted user never bounces off a forbidden redirect. Any URL the user was
 * originally headed to (via {@see redirect()->intended()}) still takes precedence.
 */
class LoginResponse implements LoginResponseContract
{
    /** Preferred landing route once authenticated. */
    private const HOME_ROUTE = 'soas.dashboard';

    /** Fallback for users without access to the preferred route. */
    private const FALLBACK_ROUTE = 'dashboard';

    public function toResponse($request): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        return redirect()->intended($this->homeUrl($request));
    }

    /**
     * Resolve the best landing URL for the authenticated user.
     */
    private function homeUrl(Request $request): string
    {
        $user = $request->user();

        return $user && $user->can(self::HOME_ROUTE)
            ? route(self::HOME_ROUTE)
            : route(self::FALLBACK_ROUTE);
    }
}
