# Laravel 12 Billing Reminders - Quick Start

## ⚡ Fastest Setup (3 Steps)

### Step 1: Verify Scheduler Configuration ✓
Already configured in `bootstrap/app.php` (lines 50-62)
```
✓ Runs daily at 2:00 AM
✓ Queued processing
✓ Single server only
✓ Auto-restart on failure
```

### Step 2: Add Cron Job to Production Server

```bash
# SSH to your server
ssh user@your-server.com

# Open crontab editor
crontab -e

# Paste this line:
* * * * * cd /path/to/esoa && php artisan schedule:run >> /dev/null 2>&1

# Save (Ctrl+X, Y, Enter)
```

### Step 3: Start Queue Worker

**Option A: Development**
```bash
php artisan queue:work --daemon
```

**Option B: Production (using Supervisor)**
Create `/etc/supervisor/conf.d/laravel-worker.conf`:
```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/esoa/artisan queue:work --queue=default --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/path/to/esoa/storage/logs/worker.log
stopwaitsecs=3600
```

Then:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

---

## 🧪 Test It Now (Development)

### Test Mode 1: Watch Scheduler
```bash
php artisan schedule:work

# Shows when jobs execute
# Output: "Running [billing:send-due-reminders]"
```

### Test Mode 2: View Scheduled Tasks
```bash
php artisan schedule:list

# Shows:
# billing:send-due-reminders    daily 02:00    next run tomorrow
```

### Test Mode 3: Run Manually
```bash
php artisan billing:send-due-reminders

# Sends reminders immediately
```

### Test Mode 4: Use Log Driver (No Real Emails)
Edit `.env`:
```env
MAIL_MAILER=log  # Change from smtp
```

Run:
```bash
php artisan billing:send-due-reminders

# Check logs:
tail -f storage/logs/laravel.log | grep "To:"
```

---

## 📋 Execution Methods

| How | Command | Use Case |
|-----|---------|----------|
| **Automatic** | Cron + Scheduler | Production ✓ |
| **Watch** | `schedule:work` | Development |
| **Manual** | `artisan billing:send-due-reminders` | On-demand |
| **Monitor** | `queue:list` | Check status |
| **Process Jobs** | `queue:work` | Required for queue |

---

## ✅ Verification Checklist

```
□ Scheduler configured in bootstrap/app.php
□ Cron job added to production server
□ Queue worker running (php artisan queue:work)
□ Mail driver configured (.env MAIL_MAILER)
□ Database has SOAs with due dates
□ Users have valid email addresses
□ Storage/logs directory is writable
□ Test email sent successfully
```

---

## 🚨 Common Issues

**Q: Jobs not running?**
```bash
# Check if queue worker is running
ps aux | grep "queue:work"

# If not, start it:
php artisan queue:work --daemon
```

**Q: No emails sent?**
```bash
# Check mail config:
php artisan config:show mail

# Test mail driver:
php artisan tinker
>>> Mail::raw('Test', fn($m) => $m->to('test@email.com')->subject('Test'))
```

**Q: Cron not triggering?**
```bash
# Check crontab:
crontab -l | grep schedule:run

# Verify cron is running:
sudo systemctl status cron
```

---

## 📚 Full Documentation

See [BILLING_REMINDERS_EXECUTION_GUIDE_LARAVEL12.md](./BILLING_REMINDERS_EXECUTION_GUIDE_LARAVEL12.md) for:
- Detailed setup instructions
- Queue configuration options
- Performance monitoring
- Troubleshooting
- Environment variables

---

## 🎯 Current Configuration

**File**: `bootstrap/app.php` (lines 50-62)

```php
->withSchedule(function ($schedule) {
    $schedule->command('billing:send-due-reminders')
        ->daily()
        ->at('02:00')                  // 2:00 AM daily
        ->queue('default')             // Async queue
        ->onOneServer()                // Distributed setup
        ->withoutOverlapping(timeout: 3600)  // Prevent overlap
        ->onFailure(fn() => Log::error('Billing reminders failed'))
        ->onSuccess(fn() => Log::info('Billing reminders succeeded'));
})
```

**Adjust timing?** Change `->at('02:00')` to any time:
```php
->at('14:00')         // 2:00 PM
->everyFiveMinutes()  // Testing only
->hourly()            // Every hour
```

---

## 🚀 Next 5 Minutes

1. Run: `php artisan schedule:list` ← Verify it's there
2. Run: `php artisan queue:work --daemon` ← Start queue worker
3. Run: `php artisan billing:send-due-reminders` ← Test manually
4. Check: `tail -f storage/logs/laravel.log` ← Monitor execution
5. Add: Cron job to production server

---

## 📞 Need Help?

1. Check logs: `tail storage/logs/laravel.log | grep billing`
2. See guide: [BILLING_REMINDERS_EXECUTION_GUIDE_LARAVEL12.md](./BILLING_REMINDERS_EXECUTION_GUIDE_LARAVEL12.md)
3. Test manually: `php artisan billing:send-due-reminders`
