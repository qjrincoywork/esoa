# Billing Invoice Due Reminder System

## Overview

This production-ready system automatically sends email reminders to users when they have billing invoices (SOAs) that are past due or coming due based on configurable aging buckets.

## System Architecture

### Components

1. **Mail Class**: `App\Mail\BillingInvoiceDueReminder`
   - Responsible for email composition
   - Accepts collection of SOAs and aging label
   - Uses reusable email template system

2. **Master Job**: `App\Jobs\SendBillingInvoiceDueReminders`
   - Orchestrator job that runs once to coordinate the entire process
   - Fetches all SOAs grouped by user and aging bucket
   - Chunks data into 2000-item batches
   - Dispatches individual reminder jobs to queue

3. **Worker Job**: `App\Jobs\SendBillingInvoiceDueReminderJob`
   - Processes each 2000-item chunk
   - Sends emails to users
   - Implements retry logic with exponential backoff
   - Provides granular error handling

4. **Artisan Command**: `SendBillingInvoiceDueRemindersCommand`
   - CLI interface to dispatch the reminder system
   - Supports queue selection and delay options
   - Provides user-friendly feedback

### Email Template

**Location**: `resources/views/emails/esoa/billing-invoice-due-reminder.blade.php`

Displays:
- User greeting
- Aging bucket information
- Table with SOA details (SOA number, account code, due date, amount)
- Payment reminder and action items

### Language Support

**Location**: `lang/en/labels.php`

Includes all email text labels with support for future translations:
- Email subject line
- Greeting messages
- Content sections
- Call-to-action text

## Production Features

### Chunking Strategy

- **Chunk Size**: 2000 SOAs per job
- **Rationale**: Prevents memory exhaustion and allows parallel processing
- **Benefit**: Multiple jobs can run simultaneously without overloading the system

### Retry Logic

- **Attempts**: 3 retries per job
- **Backoff**: Exponential [60s, 300s, 900s]
- **Timeout**: 30 minutes per job
- **Failure Handling**: Individual user failures don't block the entire batch

### Error Handling

- **Logging**: All operations logged to Laravel logs
- **Graceful Degradation**: Missing/invalid user data doesn't crash the system
- **Failed Job Reporting**: Comprehensive error messages for failed batches

### Performance Optimizations

1. **Efficient Database Queries**
   - Direct query execution with raw SQL for date comparisons
   - Minimal N+1 queries
   - Single query per aging bucket

2. **Data Grouping**
   - SOAs grouped by user_id and aging bucket
   - Reduces email sends and database hits
   - Aggregates related reminders

3. **Queue Processing**
   - Asynchronous email delivery
   - Prevents web request timeouts
   - Scalable to thousands of users

## Usage

### Command Line

#### Basic Usage
```bash
php artisan billing:send-due-reminders
```

#### With Custom Queue
```bash
php artisan billing:send-due-reminders --queue=emails
```

#### With Delay (e.g., schedule for later)
```bash
php artisan billing:send-due-reminders --delay=3600
```

#### Combined Options
```bash
php artisan billing:send-due-reminders --queue=high-priority --delay=300
```

### Scheduling

Add to `app/Console/Kernel.php` to run automatically:

```php
$schedule->command('billing:send-due-reminders')
    ->daily()
    ->at('02:00')
    ->queue('emails')
    ->onOneServer();
```

Or run weekly:

```php
$schedule->command('billing:send-due-reminders')
    ->weekly()
    ->mondays()
    ->at('03:00')
    ->queue('emails');
```

### Manual Dispatch from Code

```php
use App\Jobs\SendBillingInvoiceDueReminders;

// Basic dispatch
SendBillingInvoiceDueReminders::dispatch();

// With queue selection
SendBillingInvoiceDueReminders::dispatch()->onQueue('emails');

// With delay
SendBillingInvoiceDueReminders::dispatch()->delay(now()->addHours(1));
```

## Aging Buckets

The system monitors these aging categories defined in `SoaAging` enum:

| Bucket | Days Range | Label |
|--------|-----------|-------|
| PAST_DUE | < 0 | Past Due |
| DUE_WITHIN_30_DAYS | 1-30 | Due within 30 days |
| DUE_WITHIN_60_DAYS | 31-60 | Due within 60 days |
| DUE_WITHIN_90_DAYS | 61-90 | Due within 90 days |
| DUE_WITHIN_120_DAYS | 91-120 | Due within 120 days |
| DUE_WITHIN_MORE_THAN_120_DAYS | > 120 | Due within more than 120 days |

## Configuration

### Queue Configuration

Update `.env` to set default queue driver (recommend `database` or `redis` for production):

```env
QUEUE_CONNECTION=redis
REDIS_QUEUE_DB=1
```

### Mail Configuration

Ensure mail driver is properly configured in `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
```

### Logging

Monitor job execution in `storage/logs/laravel.log`:

```
[2024-05-17 02:00:15] local.INFO: Starting SendBillingInvoiceDueReminders job
[2024-05-17 02:00:20] local.INFO: Found 150 users with due SOAs
[2024-05-17 02:00:21] local.INFO: Successfully dispatched all reminder jobs
```

## Database Requirements

Ensure the `soas` table has these columns:
- `id` (primary key)
- `user_id` (foreign key)
- `soa_number`
- `account_code`
- `due_date` (DATE or DATETIME)
- `amount` (DECIMAL)
- `status` (INT, matches SoaStatus enum)
- `created_at` / `updated_at` (timestamps)

## Exclusions

The following SOA statuses are **excluded** from reminders:
- `PAID` - Already settled

Other statuses like `ENDORSED`, `DISPUTED` are included as they may still be pending payment.

## Monitoring & Debugging

### Check Job Status

```bash
# List failed jobs (if using database queue)
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Forget failed jobs
php artisan queue:forget {id}
```

### View Logs

```bash
# Follow logs in real-time
tail -f storage/logs/laravel.log | grep "SendBillingInvoice"

# Search for specific user's email sends
grep "user_id.*12345" storage/logs/laravel.log
```

### Test Run

To test without sending real emails, use the `testing` mail driver:

```env
MAIL_MAILER=log
```

Then check `storage/logs/laravel.log` for email details.

## Best Practices

1. **Run During Off-Peak Hours**: Schedule for early morning or late evening
2. **Monitor Queue**: Check queue length before major runs
3. **Database Backups**: Ensure backups before production runs
4. **Test First**: Run in staging environment first
5. **Email Testing**: Use sandbox SMTP before production
6. **Log Review**: Regularly check logs for errors
7. **User Feedback**: Monitor bounce rates and user reports

## Troubleshooting

### No Emails Sent
- Check if SOAs exist with due dates in configured buckets
- Verify users have valid email addresses
- Confirm mail driver configuration
- Check queue worker is running: `php artisan queue:work`

### High Memory Usage
- Reduce chunk size from 2000 to 1000 in `SendBillingInvoiceDueReminders`
- Ensure queue worker has adequate memory allocation
- Check for large resultsets in database

### Duplicate Emails
- Verify queue worker isn't running multiple instances processing same batch
- Use `onOneServer()` in scheduler
- Check for job retry duplicates in queue

### Slow Performance
- Add indexes to: `soas.user_id`, `soas.due_date`, `soas.status`
- Consider archiving old SOAs
- Check database query performance with `EXPLAIN`

## Security Considerations

1. **User Privacy**: Emails only sent to authenticated users
2. **Rate Limiting**: Implement rate limiting if needed
3. **Email Validation**: Users must have valid email addresses
4. **GDPR Compliance**: Consider unsubscribe mechanisms
5. **Audit Trail**: All sends logged with user IDs

## Future Enhancements

1. User preference for reminder frequency
2. SMS notifications as alternative
3. Email template customization per company
4. Webhook notifications to external systems
5. Advance notice configuration per aging bucket

## Support

For issues or questions:
1. Check logs in `storage/logs/laravel.log`
2. Review this documentation
3. Test in staging environment
4. Contact development team with logs and specifics
