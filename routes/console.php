<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Laravel 12 Scheduler Configuration
|--------------------------------------------------------------------------
|
| In Laravel 12, scheduled commands are configured in bootstrap/app.php
| using the ->withSchedule() method. This allows centralized management
| of all scheduled tasks in one place.
|
| Scheduled Commands:
| - billing:send-due-reminders: Runs daily at 02:00 AM to send billing
|   invoice due reminders. Configured with:
|   * Daily execution at 02:00 AM
|   * Queue-based processing
|   * One server only (distributed setup support)
|   * Overlap prevention (1-hour timeout)
|   * Success/failure callbacks for logging
|
| To run the scheduler in production, add to your cron:
| * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
|
| For development/testing:
| - Run: php artisan schedule:work    (watches and runs scheduler)
| - Test: php artisan schedule:list   (shows all scheduled tasks)
|
*/

