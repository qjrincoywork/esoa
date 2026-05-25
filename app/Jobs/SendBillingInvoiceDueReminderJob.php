<?php

namespace App\Jobs;

use App\Models\User;
use App\Mail\BillingInvoiceDueReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendBillingInvoiceDueReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * User SOA groups to process
     *
     * @var array<int, array{user_id: int, aging_value: int, soa_count: int}>
     */
    private array $userGroups;

    /**
     * Create a new job instance.
     *
     * @param array $userGroups
     */
    public function __construct(array $userGroups)
    {
        $this->userGroups = $userGroups;
        $this->queue = 'default';
        $this->tries = 4;
        $this->timeout = config('vc.overlapping_timeout'); // 1 hour
        $this->backoff = [60, 300, 900]; // Retry with exponential backoff
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Processing batch of ' . count($this->userGroups) . ' user groups');

            foreach ($this->userGroups as $userGroup) {
                $this->sendReminderForUser($userGroup);
            }

            Log::info('Successfully completed batch processing');
        } catch (\Exception $e) {
            Log::error('Error in SendBillingInvoiceDueReminderJob: ' . $e->getMessage(), [
                'exception' => $e,
                'batch_size' => count($this->userGroups),
            ]);
            throw $e;
        }
    }

    /**
     * Send reminder email to a user for their SOAs.
     *
     * @param array{user_id: int, aging_value: int, soa_count: int} $userGroup
     */
    private function sendReminderForUser(array $userGroup): void
    {
        try {
            $userId = $userGroup['user_id'];
            $agingValue = $userGroup['aging_value'];
            $soaCount = $userGroup['soa_count'];

            $user = User::select('id', 'email', 'username')
                ->find($userId);

            if (!$user || empty($user->email)) {
                Log::warning('User not found or has no email', ['user_id' => $userId]);
                return;
            }

            Mail::to($user->email)
                ->send(new BillingInvoiceDueReminder($agingValue, $soaCount));

            Log::info('Sent due reminder email', [
                'user_id' => $userId,
                'email' => $user->email,
                'aging_value' => $agingValue,
                'soa_count' => $soaCount,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send reminder email for user', [
                'user_id' => $userGroup['user_id'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            // Continue processing other users instead of failing the entire job
        }
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendBillingInvoiceDueReminderJob failed after retries', [
            'batch_size' => count($this->userGroups),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
