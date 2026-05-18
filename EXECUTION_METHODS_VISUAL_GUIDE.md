# Billing Reminders Execution Methods - Visual Guide

## 📊 Quick Comparison

```
┌─────────────────────────────────────────────────────────────────────┐
│                      EXECUTION METHODS                              │
└─────────────────────────────────────────────────────────────────────┘

╔════════════════════╦═══════════════════╦═════════════════════╗
║  PRODUCTION        ║   TESTING         ║    MANUAL           ║
║  (Automated)       ║   (Development)   ║    (On-Demand)      ║
╠════════════════════╬═══════════════════╬═════════════════════╣
║  Cron + Scheduler  ║  Watch Scheduler  ║  Direct Command     ║
║  * * * * *         ║  schedule:work    ║  artisan billing:.. ║
║  Daily @ 2:00 AM   ║  Real-time view   ║  Immediate          ║
║  Fully automatic   ║  for dev          ║  On request         ║
╚════════════════════╩═══════════════════╩═════════════════════╝
```

---

## 🚀 Method 1: AUTOMATIC (Production)

**Best for**: Production servers running 24/7

### Setup
```bash
# Add to crontab (runs every minute)
* * * * * cd /path/to/esoa && php artisan schedule:run >> /dev/null 2>&1
```

### How it works
1. Cron runs every minute
2. Laravel checks scheduled tasks
3. At 2:00 AM daily, job queues automatically
4. Queue worker processes emails

### Verification
```bash
# Check scheduled tasks
php artisan schedule:list

# Check cron is running
crontab -l

# Monitor logs
tail -f storage/logs/laravel.log | grep billing
```

### Pros ✅
- Automatic, fire-and-forget
- Zero manual intervention
- Reliable for repeating tasks
- Perfect for production

### Cons ❌
- Requires cron access
- Separate queue worker needed
- No real-time visibility

---

## 👀 Method 2: WATCH MODE (Development)

**Best for**: Development, testing, debugging

### Setup
```bash
# Terminal 1: Watch the scheduler
php artisan schedule:work

# Terminal 2: Process jobs
php artisan queue:work
```

### How it works
1. `schedule:work` watches scheduler continuously
2. Shows when jobs execute in real-time
3. `queue:work` processes jobs immediately
4. You see everything live

### Verification
```bash
# Terminal output shows:
Running [billing:send-due-reminders]

# You can watch it execute
```

### Pros ✅
- See everything in real-time
- Great for debugging
- No cron needed
- Easy to test changes

### Cons ❌
- Manual - must keep terminal open
- Not for production
- Only works on dev machine

---

## ⚡ Method 3: MANUAL COMMAND (On-Demand)

**Best for**: Testing, one-off runs, testing logic

### Setup
```bash
# Just run the command
php artisan billing:send-due-reminders
```

### Available Options
```bash
# Standard (queued)
php artisan billing:send-due-reminders

# Immediate execution (no queue)
php artisan billing:send-due-reminders --sync

# Custom queue
php artisan billing:send-due-reminders --queue=emails

# With delay (in seconds)
php artisan billing:send-due-reminders --delay=300

# Combine options
php artisan billing:send-due-reminders --queue=high --delay=600 --sync
```

### Verification
```bash
# Standard execution (queued)
php artisan billing:send-due-reminders
# → Check: php artisan queue:list

# Synchronous (immediate)
php artisan billing:send-due-reminders --sync
# → Emails sent immediately
```

### Pros ✅
- Immediate execution
- Full control
- Great for testing
- No dependencies

### Cons ❌
- Must run manually
- Not automatic
- Easy to forget

---

## 🔍 Detailed Comparison Table

```
┌──────────────────┬─────────────────┬──────────────────┬──────────────────┐
│ Feature          │ Automatic       │ Watch Mode       │ Manual Command   │
├──────────────────┼─────────────────┼──────────────────┼──────────────────┤
│ Setup            │ Add cron job    │ Run commands     │ Run 1 command    │
│ Effort           │ One-time        │ Each session     │ Each time        │
│ Frequency        │ Daily @ 2 AM    │ Custom           │ On demand        │
│ Visibility       │ Low (logs)      │ High (live)      │ Immediate        │
│ Best For         │ Production      │ Development      │ Testing/Debug    │
│ Cron Needed      │ Yes             │ No               │ No               │
│ Queue Worker     │ Yes (separate)  │ Yes (separate)   │ Optional         │
│ Effort/Month     │ None (set once) │ Terminal window  │ Manual each time │
│ Reliability      │ Very High       │ High             │ Manual dependent │
│ Scalability      │ Excellent       │ Limited          │ Limited          │
│ Multi-server     │ Works (onOne)   │ Single only      │ Single only      │
└──────────────────┴─────────────────┴──────────────────┴──────────────────┘
```

---

## 🎯 When to Use Each

### Use AUTOMATIC if:
- ✅ Production environment
- ✅ Running 24/7
- ✅ Need reliable scheduling
- ✅ Multiple servers (load balanced)

### Use WATCH MODE if:
- ✅ Local development
- ✅ Testing scheduler behavior
- ✅ Debugging issues
- ✅ Understanding flow

### Use MANUAL if:
- ✅ Testing specific scenarios
- ✅ One-off execution needed
- ✅ Debugging job logic
- ✅ Running from CI/CD pipeline

---

## 📋 Step-by-Step Examples

### Example 1: Production Setup
```bash
# 1. Add cron (one-time on server)
crontab -e
# Add: * * * * * cd /path/to/esoa && php artisan schedule:run >> /dev/null 2>&1

# 2. Start queue worker (permanent, via Supervisor)
# See: BILLING_REMINDERS_EXECUTION_GUIDE_LARAVEL12.md Section 6

# 3. Verify
php artisan schedule:list  # Should show daily @ 02:00
ps aux | grep queue:work   # Worker running

# Done! It runs automatically every day at 2:00 AM
```

### Example 2: Development Testing
```bash
# Terminal 1: Watch scheduler
php artisan schedule:work

# Terminal 2: Process jobs
php artisan queue:work

# Output shows when tasks run
# Can test by changing scheduler time temporarily:
# ->everyFiveMinutes()  // For testing
```

### Example 3: Manual Testing
```bash
# Test with log driver (no real emails)
# .env: MAIL_MAILER=log

# Run immediately (no queue)
php artisan billing:send-due-reminders --sync

# Check logs
tail storage/logs/laravel.log | grep "To:"

# See the emails that would be sent
```

---

## 🔄 Decision Tree

```
                    Need to run billing reminders?
                               │
                    ┌──────────┴──────────┐
                    │                     │
            Regular schedule?      Ad-hoc/testing?
                    │                     │
                YES │                  NO │
                    │                     │
        ┌───────────▼──────────┐    ┌────▼────────────┐
        │ AUTOMATIC (Cron)     │    │ MANUAL COMMAND  │
        │ Production ready ✓   │    │ One-time ✓      │
        └──────────────────────┘    └────────────────┘
                    │
        ┌───────────▼──────────────────┐
        │ Need real-time visibility?   │
        └───────────┬──────────────────┘
                    │
        ┌───────────┴──────────┐
        │                      │
      YES │                NO │
        │                      │
    ┌───▼────────┐      ┌─────▼──────┐
    │ WATCH MODE │      │ PRODUCTION │
    │ Dev only   │      │ Prod ready │
    └────────────┘      └────────────┘
```

---

## 💻 Command Cheat Sheet

```bash
# VIEW & MONITOR
php artisan schedule:list              # See all scheduled tasks
php artisan queue:list                 # See pending jobs
php artisan queue:failed               # See failed jobs
tail -f storage/logs/laravel.log       # Stream logs

# DEVELOPMENT
php artisan schedule:work              # Watch scheduler (dev)
php artisan queue:work --daemon        # Process jobs (dev)
php artisan schedule:work --verbose    # Verbose output

# PRODUCTION SETUP
crontab -e                             # Add cron job
# * * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1

# MANUAL EXECUTION
php artisan billing:send-due-reminders              # Standard
php artisan billing:send-due-reminders --sync       # Immediate
php artisan billing:send-due-reminders --queue=e   # Custom queue
php artisan billing:send-due-reminders --delay=300  # Delayed

# QUEUE MANAGEMENT
php artisan queue:work                 # Start worker
php artisan queue:work --queue=high    # Specific queue
php artisan queue:retry all            # Retry failed
php artisan queue:forget {id}          # Remove failed
php artisan queue:flush                # Clear all failed

# TESTING
MAIL_MAILER=log php artisan billing:send-due-reminders --sync
# Then: tail storage/logs/laravel.log | grep "To:"
```

---

## 🎓 Understanding the Flow

### Automatic Flow
```
Time: Every minute
Cron → schedule:run → Check if 2:00 AM → YES → Queue job
                                         → NO  → Do nothing

When job is in queue:
Queue → queue:work → Load SendBillingInvoiceDueReminders
                  → Fetch SOAs by user & aging
                  → Dispatch SendBillingInvoiceDueReminderJob (2000 chunks)
                  → Each chunk → Send emails to users
                  → Retry on failure (3x with backoff)
                  → Log success/failure
```

### Manual Flow
```
You type: php artisan billing:send-due-reminders --sync

Command → Create SendBillingInvoiceDueReminders
       → Call handle() directly (--sync)
       → OR Queue job (default)
       
If --sync (immediate):
       → Execute right now
       → Fetch SOAs
       → Send emails
       → Show result
       
If default (queued):
       → Queue job
       → Queue:work must process it
       → Eventually emails sent
```

---

## ✅ Quick Decision Matrix

| I want to... | Use This | Command |
|---|---|---|
| Run automatically daily | Automatic | `crontab -e` |
| See it working in real-time | Watch Mode | `schedule:work` |
| Test right now | Manual Sync | `--sync` |
| Test with queue | Manual Queued | default |
| Test emails without sending | Watch + Log Driver | `MAIL_MAILER=log` |
| Debug queue issues | Manual + Logs | `tail -f logs` |
| Run from CI/CD | Manual | artisan command |
| Run once per month | Manual | schedule it in CI |

---

## 🚨 Troubleshooting by Method

### Automatic not working?
```bash
# Check 1: Cron job exists
crontab -l | grep schedule:run

# Check 2: PHP works
php -v

# Check 3: Scheduler works
php artisan schedule:list

# Check 4: Cron runs
ps aux | grep cron

# Check 5: Logs show execution
tail storage/logs/laravel.log | grep schedule
```

### Watch mode not showing anything?
```bash
# Check 1: Terminal open?
ps aux | grep schedule:work

# Check 2: Queue worker running?
ps aux | grep queue:work

# Check 3: Check for errors
php artisan schedule:list

# Check 4: Try running task manually
php artisan billing:send-due-reminders --sync
```

### Manual command not executing?
```bash
# Check 1: Command exists
php artisan list | grep billing

# Check 2: Try --sync mode
php artisan billing:send-due-reminders --sync

# Check 3: Check for errors in code
php artisan tinker
# Then manually inspect job

# Check 4: Queue worker needed?
php artisan queue:work
```

---

## 📞 Need More Help?

- **Quick Start**: `BILLING_REMINDERS_QUICK_START.md`
- **Detailed Guide**: `BILLING_REMINDERS_EXECUTION_GUIDE_LARAVEL12.md`
- **Laravel 12 Summary**: `LARAVEL12_BILLING_REMINDERS_SUMMARY.md`
- **Original Guide**: `BILLING_INVOICE_DUE_REMINDER_GUIDE.md`

---

**Status**: ✅ Production Ready  
**Laravel**: 12.x  
**Last Updated**: May 17, 2026
