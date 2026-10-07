<?php

namespace App\Providers;

use App\Contracts\Dashboard\SoaMetricsRepository;
use App\Contracts\Dashboard\UserActivityRepository;
use App\Repositories\Dashboard\EloquentSoaMetricsRepository;
use App\Repositories\Dashboard\EloquentUserActivityRepository;
use App\Services\RoutePermissionService;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
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

        $this->syncRoutePermissionsAfterMigrating();
    }

    /**
     * Register a permission for every newly guarded route whenever migrations run.
     *
     * Deploys already run `migrate`, so a route added behind `check_permissions` becomes
     * grantable on the next deploy without a hand-written permission migration. Hooked on
     * the command finishing rather than on MigrationsEnded, which never fires when there
     * is nothing to migrate — the usual case for a deploy that only adds a route. Skipped
     * when migrate failed or ran with --pretend, and a failure here is logged rather than
     * failing the run that triggered it.
     */
    private function syncRoutePermissionsAfterMigrating(): void
    {
        Event::listen(CommandFinished::class, function (CommandFinished $event): void {
            if ($event->command !== 'migrate'
                || $event->exitCode !== 0
                || ($event->input->hasOption('pretend') && $event->input->getOption('pretend'))) {
                return;
            }

            try {
                $created = app(RoutePermissionService::class)->sync();

                if ($created->isNotEmpty()) {
                    Log::info('Registered route permissions after migrating', ['permissions' => $created->all()]);
                }
            } catch (\Throwable $e) {
                Log::warning('Route permission sync after migrating failed', ['error' => $e->getMessage()]);
            }
        });
    }
}
