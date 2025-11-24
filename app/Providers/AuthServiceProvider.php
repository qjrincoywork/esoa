<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Spatie automatically registers all permissions as Gates when 
        // 'register_permission_check_method' => true in config/permission.php
        
        // Superadmin bypasses all permission checks via Gate
        Gate::before(function ($user, $ability) {
            if ($user && method_exists($user, 'hasRole') && $user->hasRole('superadmin')) {
                return true;
            }
        });
        
        // Ensure permissions are registered as Gates (Spatie does this automatically,
        // but we can add explicit registration if needed)
        // This is handled by Spatie's PermissionServiceProvider
    }
}
