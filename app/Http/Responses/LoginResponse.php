<?php

namespace App\Http\Responses;

use App\Support\LandingRoute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

/**
 * Post-login redirect for the application.
 *
 * Single responsibility: decide where an authenticated user lands after logging in.
 * The landing page is resolved by {@see LandingRoute}, which only ever picks a page
 * the user may open, so a user never bounces off a forbidden redirect. Any URL the
 * user was originally headed to (via {@see redirect()->intended()}) still takes
 * precedence and is guarded by that route's own middleware.
 */
class LoginResponse implements LoginResponseContract
{
    /**
     * Build the post-login response: a 204 No Content for JSON callers, otherwise
     * a redirect to the user's intended URL or the resolved landing page.
     */
    public function toResponse($request): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        return redirect()->intended(LandingRoute::urlFor($request->user()));
    }
}
