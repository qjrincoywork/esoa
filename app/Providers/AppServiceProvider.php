<?php

namespace App\Providers;

use App\Contracts\Dashboard\SoaMetricsRepository;
use App\Contracts\Dashboard\UserActivityRepository;
use App\Repositories\Dashboard\EloquentSoaMetricsRepository;
use App\Repositories\Dashboard\EloquentUserActivityRepository;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bind the dashboard read models to their contracts, so the reporting layer depends on
     * the interfaces and the aggregation strategy can be swapped in one place.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        SoaMetricsRepository::class => EloquentSoaMetricsRepository::class,
        UserActivityRepository::class => EloquentUserActivityRepository::class,
    ];

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
