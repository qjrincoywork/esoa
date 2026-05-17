<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // $middleware->encryptCookies( ['appearance', 'sidebar_state']);

        // $middleware->alias([
        //     'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
        //     'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        //     'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        //     // 'allow_admin_or_role' => \App\Http\Middleware\AllowAdminOrRole::class,
        //     'check_permissions' => \App\Http\Middleware\CheckPermission::class,
        // ]);
        // $middleware->web( [
        //     HandleAppearance::class,
        //     HandleInertiaRequests::class,
        //     AddLinkHeadersForPreloadedAssets::class,
        //     // \App\Http\Middleware\CheckPermission::class,
        // ]);
        $middleware->encryptCookies([
            'appearance',
            'sidebar_state',
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'check_permissions' => \App\Http\Middleware\CheckPermission::class,
        ]);

        $middleware->appendToGroup('web', [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withSchedule(function ($schedule) {
        // Billing invoice due reminders
        $schedule->command('billing:send-due-reminders')
            ->daily()
            ->at(config('vc.billing_reminder_time', '07:00'))
            ->onOneServer()
            ->withoutOverlapping(config('vc.overlapping_timeout'))
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('Billing due reminders scheduler failed');
            })
            ->onSuccess(function () {
                \Illuminate\Support\Facades\Log::info('Billing due reminders executed successfully');
            });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
