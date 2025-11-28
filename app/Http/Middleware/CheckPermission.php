<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return redirect()->route('login');
        }
        // Get full route name e.g., "users.update"
        $routeName = $request->route()->getName();
        if (!$routeName) {
            return abort(Response::HTTP_FORBIDDEN, 'Route has no name.');
        }

        // Superadmin bypasses all permission checks
        if ($user->hasRole('superadmin')) {
            return $next($request);
        }

        // Check if user has the required permission
        $hasPermission = $user->hasPermissionTo($routeName);

        if (!$hasPermission) {
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(
                    ['message' => 'You do not have permission to access this resource'],
                    Response::HTTP_FORBIDDEN
                );
            }

            // Redirect to dashboard with error message for toast display
            // return abort(Response::HTTP_FORBIDDEN, 'You do not have permission to access this resource.');
            return redirect()->route('dashboard')->with('error', 'You do not have permission to access this resource');
        }

        return $next($request);
    }
}
