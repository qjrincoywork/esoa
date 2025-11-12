<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowAdminOrRole
{
    /**
     * Handle an incoming request.
     *
     * This middleware allows access if the user is an admin OR has the specified role.
     * Superadmins can access all routes, while other roles can only access their specific routes.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Unauthorized');
        }

        // Superadmin can access everything
        if ($user->hasRole('superadmin')) {
            return $next($request);
        }

        // Check if user has the specified role
        if (!$user->hasRole($role)) {
            abort(403, 'Access denied. You do not have the required role.');
        }

        return $next($request);
    }
}
