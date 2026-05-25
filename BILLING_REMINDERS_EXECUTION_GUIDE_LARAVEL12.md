# Laravel 12: Billing Invoice Due Reminders - Execution Guide

## Overview

This guide explains how to execute the `billing:send-due-reminders` job in **Laravel 12**. The system supports multiple execution methods with automatic scheduling.

---

## 1. Automatic Scheduling (Recommended)

### How It Works

The scheduler is configured in `bootstrap/app.php` using the modern Laravel 12 approach:

```php
->withSchedule(function ($schedule) {
    $schedule->command('billing:send-due-reminders')
        ->daily()
        ->at('02:00')              // 2:00 AM daily
        ->queue('default')         // Async via queue
        ->onOneServer()            // Only one server processes
        ->withoutOverlapping(timeout: 3600);
})
```

**Location**: `bootstrap/app.php` (lines 50-62)

### Production Setup - Add to Cron

Run this command on your server:

```bash
# Open crontab
crontab -e

# Add this single line:
* * * * * cd /path/to/esoa && php artisan schedule:run >> /dev/null 2>&1
```

This checks every minute if any scheduled tasks need to run. At 2:00 AM daily, your billing reminders will automatically execute.

### Test the Scheduler

```bash
# View all scheduled tasks
php artisan schedule:list

# Expected output should show:
# Command: billing:send-due-reminders
# Interval: daily at 02:00
# Next Run: [tomorrow at 02:00]
```

---

## 2. Watch Mode (Development)

Perfect for testing and development:

```bash
# Watches and executes scheduler in real-time
php artisan schedule:work

# Output shows when tasks run
# e.g., "Running [billing:send-due-reminders] ..."
```

Leave this running in a terminal during development to test scheduled tasks immediately.

---

## 3. Manual Execution (Testing/Debugging)

### Run Directly

```bash
# Dispatch job immediately
php artisan billing:send-due-reminders
```

### With Custom Queue

```bash
# Send to specific queue
php artisan billing:send-due-reminders --queue=emails
```

### With Delay

```bash
# Delay job execution by 5 minutes (300 seconds)
php artisan billing:send-due-reminders --delay=300
```

### Combined Options

```bash
# High-priority queue with 10-minute delay
php artisan billing:send-due-reminders --queue=high-priority --delay=600
```

---

## 4. Programmatic Dispatch (From Code)

### Basic Dispatch

```php
use App\Jobs\SendBillingInvoiceDueReminders;

// In a controller, service, or command:
SendBillingInvoiceDueReminders::dispatch();
```

### With Queue Selection

```php
SendBillingInvoiceDueReminders::dispatch()
    ->onQueue('emails');
```

### With Delay

```php
use Illuminate\Support\Facades\Bus;

SendBillingInvoiceDueReminders::dispatch()
    ->delay(now()->addHours(2));
```

### Chained Dispatch

```php
SendBillingInvoiceDueReminders::dispatch()
    ->onQueue('emails')
    ->delay(now()->addMinutes(30));
```

---

## 5. Job Monitoring & Queue Management

### Monitor Queue Status

```bash
# List pending jobs
php artisan queue:list

# View failed jobs
php artisan queue:failed

# Retry all failed jobs
php artisan queue:retry all

# Forget specific failed job
php artisan queue:forget {job-id}

# Forget all failed jobs
php artisan queue:flush
```

### Monitor Execution

```bash
# Check logs in real-time
tail -f storage/logs/laravel.log | grep "SendBillingInvoice"

# Search for specific user
grep "user_id.*12345" storage/logs/laravel.log
```

### Run Queue Worker

For the jobs to actually execute, a queue worker must be running:

```bash
# Basic queue worker (processes default queue)
php artisan queue:work

# Process high-priority queue first
php artisan queue:work --queue=high-priority,default

# Daemon mode with auto-reload
php artisan queue:work --daemon

# With timeout and memory limits
php artisan queue:work --timeout=3600 --memory=512
```

For production, use a process manager like **Supervisor**:

```ini
# /etc/supervisor/conf.d/laravel-worker.conf
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/esoa/artisan queue:work --queue=default,emails --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/path/to/esoa/storage/logs/worker.log
```

Then reload:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

---

## 6. Environment Configuration

### Queue Driver (.env)

```env
# Use database queue (recommended for small/medium setups)
QUEUE_CONNECTION=database

# Or use Redis for better performance
QUEUE_CONNECTION=redis
REDIS_QUEUE=default
REDIS_QUEUE_DB=1

# Or use Beanstalk
QUEUE_CONNECTION=beanstalk
```

### Mail Configuration (.env)

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
```

### Schedule Timing (.env)

The schedule runs at **02:00 AM UTC** daily. Adjust in `bootstrap/app.php` if needed:

```php
->at('14:00')  // 2:00 PM UTC
->at('02:30')  // 2:30 AM UTC
->everyFiveMinutes()  // Every 5 minutes (for testing)
->everyMinute()  // Every minute (for testing)
```

---

## 7. Testing Modes

### Test with Log Driver (No Real Emails)

Update `.env`:

```env
MAIL_MAILER=log
```

Run the command:

```bash
php artisan billing:send-due-reminders
```

Check logs:

```bash
tail storage/logs/laravel.log | grep -A 30 "To:"
```

### Test Single User

Create a test command to verify for specific user:

```bash
# In routes/console.php, add temporary command:
Artisan::command('test:billing-reminder', function () {
    $user = \App\Models\User::find(123);
    $soas = \App\Models\Soa::where('user_id', 123)
        ->where('status', '!=', \App\Enums\SoaStatus::PAID)
        ->limit(5)
        ->get();
    
    \Mail::to($user->email)->send(
        new \App\Mail\BillingInvoiceDueReminder($soas, 'Past Due')
    );
    
    $this->info('Test email sent to: ' . $user->email);
})->purpose('Test billing reminder for user');

# Run:
php artisan test:billing-reminder
```

---

## 8. Performance Monitoring

### Check Scheduled Task Execution

```bash
# View last execution
php artisan schedule:finish-command billing:send-due-reminders

# View command history
php artisan schedule:list --verbose
```

### Monitor Memory Usage

```bash
# Watch queue worker memory
watch 'ps aux | grep "queue:work"'

# Check process details
ps -aux | grep artisan
```

### Database Query Monitoring

Enable query logging in `.env`:

```env
DB_QUERY_LOG=true
```

Then check logs:

```bash
grep "SELECT.*from.*soas" storage/logs/laravel.log
```

---

## 9. Troubleshooting

### Jobs Not Running

**Check 1**: Scheduler running?
```bash
ps aux | grep "schedule:run"
```

**Check 2**: Cron job exists?
```bash
crontab -l | grep "schedule:run"
```

**Check 3**: Queue worker running?
```bash
ps aux | grep "queue:work"
```

### No Emails Sent

**Check 1**: Verify SOAs exist in due buckets
```bash
php artisan tinker
>>> \App\Models\Soa::where('status', '!=', \App\Enums\SoaStatus::PAID)->count()
```

**Check 2**: Check mail configuration
```bash
php artisan config:show mail
```

**Check 3**: Test email directly
```bash
Mail::raw('Test', function ($msg) {
    $msg->to('test@example.com')->subject('Test');
});
```

### High Memory Usage

Reduce chunk size in `SendBillingInvoiceDueReminders.php`:

```php
private const CHUNK_SIZE = 1000;  // Was 2000
```

Then clear job queue and reprocess.

---

## 10. Optimization Tips for Production

### 1. Use Redis for Queue
Redis is much faster than database queue:

```env
QUEUE_CONNECTION=redis
```

### 2. Run Multiple Workers

```bash
# In Supervisor, set numprocs higher:
numprocs=8  # Run 8 worker processes
```

### 3. Optimize Database Indexes

```sql
CREATE INDEX idx_soas_user_id ON soas(user_id);
CREATE INDEX idx_soas_due_date ON soas(due_date);
CREATE INDEX idx_soas_status ON soas(status);
```

### 4. Archive Old SOAs

```php
// In a separate job
Soa::where('status', SoaStatus::PAID)
    ->where('created_at', '<', now()->subYear())
    ->delete();
```

### 5. Monitor with Tools

```bash
# Use Horizon for queue monitoring (if Redis)
php artisan horizon:install

# Access at: http://your-app/horizon
```

---

## 11. Summary of Execution Methods

| Method | Command | When to Use |
|--------|---------|------------|
| **Automatic** | Cron + Scheduler | Production - runs daily |
| **Watch Mode** | `schedule:work` | Development/Testing |
| **Direct** | `artisan billing:send-due-reminders` | Manual/On-demand |
| **Queue** | `queue:work` | Production - processes jobs |
| **Code** | `SendBillingInvoiceDueReminders::dispatch()` | From application logic |

---

## 12. Next Steps

1. **Verify Setup**: Run `php artisan schedule:list`
2. **Test Mail**: Use log driver and check `storage/logs/laravel.log`
3. **Configure Cron**: Add to production server crontab
4. **Start Queue Worker**: Use Supervisor in production
5. **Monitor Logs**: Check `storage/logs/laravel.log` after first run
6. **Verify Emails**: Confirm users receive reminders

---

## Quick Reference

```bash
# Development Setup
php artisan schedule:work          # Watch scheduler
php artisan queue:work --daemon    # Process jobs

# Production Setup
# 1. Add to crontab: * * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
# 2. Configure Supervisor for queue:work
# 3. Monitor: php artisan queue:list

# Testing
php artisan billing:send-due-reminders --delay=0  # Run immediately
php artisan schedule:list                          # View all schedules
tail -f storage/logs/laravel.log                   # Monitor logs
```
