<?php

namespace App\Console\Commands;

use App\Jobs\SendBillingInvoiceDueReminders;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class SendBillingInvoiceDueRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:send-due-reminders {--queue=default : The queue to dispatch the job to} {--delay=0 : Delay in seconds before processing} {--sync : Run synchronously instead of queued}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send billing invoice due date reminders to users for SOAs that are past due or coming due. Runs at 2:00 AM daily via scheduler, or manually with this command.';

    /**
     * Dispatch (or, with --sync, immediately run) the SendBillingInvoiceDueReminders job,
     * honoring the --queue and --delay options, and report progress to the console.
     *
     * @return int Command exit code (self::SUCCESS on dispatch/run, self::FAILURE on exception).
     */
    public function handle(): int
    {
        try {
            $this->info('🚀 Initiating billing invoice due reminders...');
            $this->newLine();

            $queue = $this->option('queue');
            $delay = (int) $this->option('delay');
            $sync = (bool) $this->option('sync');

            // Display configuration
            $this->showConfiguration($queue, $delay, $sync);

            // Create job instance
            $job = new SendBillingInvoiceDueReminders();

            if ($sync) {
                // Run synchronously (immediate execution)
                $this->info('⏳ Running in synchronous mode (immediate execution)...');
                $this->newLine();
                $job->handle();
                $this->info('✅ Job completed successfully');
            } else {
                // Queue the job
                $job->onQueue($queue);

                if ($delay > 0) {
                    $job->delay($delay);
                }

                $job->dispatch();

                Log::info('SendBillingInvoiceDueReminders job dispatched', [
                    'queue' => $queue,
                    'delay' => $delay,
                    'timestamp' => now(),
                ]);

                $this->info("✅ Job dispatched successfully to queue: {$queue}");
                $this->info('📧 Users with due SOAs will receive reminder emails shortly.');
            }

            $this->newLine();
            $this->info('💡 Tip: Run "php artisan queue:work" to process queued jobs.');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Error dispatching billing reminder job: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());

            Log::error('Error in SendBillingInvoiceDueRemindersCommand', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Display current command configuration
     */
    private function showConfiguration(string $queue, int $delay, bool $sync): void
    {
        $this->line('📋 Configuration:');
        $this->line('  Queue: ' . ($sync ? 'SYNCHRONOUS (immediate)' : $queue));
        $this->line('  Delay: ' . ($delay > 0 ? "{$delay} seconds" : 'None'));
        $this->line('  Mode: ' . ($sync ? 'SYNC' : 'ASYNC'));

        if ($delay > 0) {
            $delayMinutes = ceil($delay / 60);
            $this->line("  ⏱️  Will run in approximately {$delayMinutes} minute(s)");
        }

        $this->newLine();
    }
}
