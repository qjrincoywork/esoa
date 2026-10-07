<?php

namespace App\Http\Middleware;

use App\Enums\UserType;
use App\Support\LandingRoute;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DenyUserType
{
    /**
     * Reject the request when the user's type is one of the listed types.
     *
     * Parameters are resolved by {@see UserType::resolve()}, e.g.
     * `deny_user_type:mappable` or `deny_user_type:BROKER,GROUP_ACCOUNT_ADMIN`.
     * This check applies to superadmins too — it runs independently of roles, so a
     * mapped account user cannot reach the route even if granted an elevated role.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$types): Response
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        if (!$user->hasUserType(UserType::resolve($types))) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(
                ['message' => 'You do not have permission to access this resource'],
                Response::HTTP_FORBIDDEN
            );
        }

        return redirect()->to(LandingRoute::urlFor($user, $request->route()?->getName()))
            ->with('error', 'You do not have permission to access this resource');
    }
}
