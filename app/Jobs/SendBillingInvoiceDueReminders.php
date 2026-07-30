<?php

namespace App\Jobs;

use App\Models\Soa;
use App\Enums\SoaStatus;
use App\Enums\SoaAging;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SendBillingInvoiceDueReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Statuses to exclude from reminders
     */
    private const EXCLUDE_STATUSES = [SoaStatus::PAID];

    /**
     * Create the job and configure its queue, retry count, and timeout.
     */
    public function __construct()
    {
        $this->queue = 'default';
        $this->tries = 3;
        $this->timeout = config('vc.overlapping_timeout'); // 1 hour timeout
    }

    /**
     * Group all non-paid SOAs by user and aging bucket and fan out reminders.
     *
     * Fetches the aged SOA counts per user (see fetchSOAsGroupedByUser), and
     * when any exist dispatches chunked SendBillingInvoiceDueReminderJob jobs.
     * Errors are logged and rethrown.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting SendBillingInvoiceDueReminders job');

            // Fetch all SOAs that are past due or coming due, grouped by user
            $userSoaGroups = $this->fetchSOAsGroupedByUser();

            if ($userSoaGroups->isEmpty()) {
                Log::info('No SOAs found for reminders');
                return;
            }

            Log::info('Found ' . $userSoaGroups->count() . ' users with due SOAs');

            // Dispatch individual reminder jobs for each user
            $this->dispatchReminderJobs($userSoaGroups);

            Log::info('Successfully dispatched all reminder jobs');
        } catch (\Exception $e) {
            Log::error('Error in SendBillingInvoiceDueReminders: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /**
     * Fetch SOAs grouped by user_id with aging classifications.
     * Returns collection keyed by user_id with aged SOAs.
     *
     * @return \Illuminate\Support\Collection<int, array{user_id: int, aging_value: int, soa_count: int}>
     */
    private function fetchSOAsGroupedByUser(): \Illuminate\Support\Collection
    {
        $soasWithAging = collect();

        // Get all active aging buckets
        $agingBuckets = SoaAging::getValues();

        foreach ($agingBuckets as $agingValue) {
            // Build query for this aging bucket
            $query = Soa::query()
                ->select('user_id', DB::raw('COUNT(*) as soa_count'))
                ->where('status', '!=', SoaStatus::PAID)
                ->groupBy('user_id');

            $this->applyAgingFilter($query, $agingValue);

            foreach ($query->get() as $row) {
                $soasWithAging->put("{$row->user_id}_{$agingValue}", [
                    'user_id' => $row->user_id,
                    'aging_value' => $agingValue,
                    'soa_count' => (int) $row->soa_count,
                ]);
            }
        }

        return $soasWithAging;
    }

    /**
     * Apply aging filter to query based on SoaAging value.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $agingValue
     */
    private function applyAgingFilter(&$query, int $agingValue): void
    {
        [$expression, $bindings] = SoaAging::sqlPredicate($agingValue);
        $query->whereRaw($expression, $bindings);
    }

    /**
     * Dispatch reminder jobs grouped by chunks and users.
     *
     * @param \Illuminate\Support\Collection $userSoaGroups
     */
    private function dispatchReminderJobs(\Illuminate\Support\Collection $userSoaGroups): void
    {
        $chunk = [];
        $chunkCount = 0;

        foreach ($userSoaGroups as $userGroup) {
            $chunk[] = $userGroup;
            $chunkCount++;

            // Dispatch when chunk reaches size limit or at the end
            if ($chunkCount >= config('vc.chunk_size')) {
                SendBillingInvoiceDueReminderJob::dispatch($chunk);
                $chunk = [];
                $chunkCount = 0;
            }
        }

        // Dispatch remaining chunk
        if (!empty($chunk)) {
            SendBillingInvoiceDueReminderJob::dispatch($chunk);
        }
    }
}
