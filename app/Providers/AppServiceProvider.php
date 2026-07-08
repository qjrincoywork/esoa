<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Set the default string column length to 191 (MySQL utf8mb4 index limit)
     * and register the application-wide strong password defaults: minimum 12
     * characters with mixed case, letters, numbers, symbols, and an
     * uncompromised (breach-check) requirement.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        Password::defaults(function () {
            return Password::min(12)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised();
        });
    }
}
