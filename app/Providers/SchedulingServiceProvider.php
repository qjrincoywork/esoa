<?php

namespace App\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Support\Providers\ScheduleServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Log;

/**
 * SchedulingServiceProvider
 *
 * OPTIONAL: Alternative approach for managing scheduled tasks in Laravel 12.
 *
 * Current Implementation: The scheduler is configured directly in bootstrap/app.php
 * using the modern ->withSchedule() method. This is the recommended approach.
 *
 * When to Use This Provider:
 * - When you have many scheduled tasks (10+) and want better organization
 * - When you prefer separating scheduling logic from bootstrap/app.php
 * - When you need to scale scheduling across multiple providers
 *
 * How to Activate (Optional):
 * 1. Register in bootstrap/app.php:
 *    ->withProviders([
 *        App\Providers\SchedulingServiceProvider::class,
 *    ])
 *
 * 2. Remove the ->withSchedule() closure from bootstrap/app.php
 *
 * 3. The schedule() method below will be called automatically
 *
 * Benefits:
 * ✓ Better code organization for large projects
 * ✓ Easier to test scheduling logic
 * ✓ Cleaner bootstrap/app.php file
 * ✓ Supports trait-based scheduling patterns
 *
 * @see bootstrap/app.php for current implementation
 */
class SchedulingServiceProvider extends ServiceProvider
{
    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // Billing Invoice Due Reminders
        // Sends reminder emails to users with SOAs that are past due or coming due
        $schedule->command('billing:send-due-reminders')
            ->daily()
            ->at('02:00')                          // Run at 2:00 AM daily
            ->queue('default')                     // Process via queue
            ->onOneServer()                        // Only run on one server in distributed setup
            ->withoutOverlapping(timeout: 3600)   // Prevent overlapping runs (1 hour timeout)
            ->onFailure(function () {
                Log::error('Billing due reminders schedule execution failed');
            })
            ->onSuccess(function () {
                Log::info('Billing due reminders schedule executed successfully');
            });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }
}
