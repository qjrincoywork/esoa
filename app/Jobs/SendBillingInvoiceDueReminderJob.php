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
use Illuminate\Support\Collection;

class SendBillingInvoiceDueReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * User SOA groups to process
     *
     * @var array<int, array{user_id: int, aging_value: int, aging_label: string, soas: Collection}>
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
        $this->tries = 3;
        $this->timeout = 1800; // 30 minutes
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
     * @param array{user_id: int, aging_value: int, aging_label: string, soas: Collection} $userGroup
     */
    private function sendReminderForUser(array $userGroup): void
    {
        try {
            $userId = $userGroup['user_id'];
            $agingLabel = $userGroup['aging_label'];
            $soas = $userGroup['soas'];

            // Fetch user with their email
            $user = User::select('id', 'email', 'username')
                ->find($userId);

            if (!$user || empty($user->email)) {
                Log::warning('User not found or has no email', ['user_id' => $userId]);
                return;
            }

            // Send email
            Mail::to($user->email)
                ->send(new BillingInvoiceDueReminder($soas, $agingLabel));

            Log::info('Sent due reminder email', [
                'user_id' => $userId,
                'email' => $user->email,
                'aging_label' => $agingLabel,
                'soa_count' => $soas->count(),
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
