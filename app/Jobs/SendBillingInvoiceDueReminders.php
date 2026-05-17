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
     * Chunk size for batch processing
     */
    private const CHUNK_SIZE = 2000;

    /**
     * Statuses to exclude from reminders
     */
    private const EXCLUDE_STATUSES = [SoaStatus::PAID];

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->queue = 'default';
        $this->tries = 3;
        $this->timeout = 3600; // 1 hour timeout
    }

    /**
     * Execute the job.
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
     * @return \Illuminate\Support\Collection<int, array{user_id: int, soas: array, aging_info: array}>
     */
    private function fetchSOAsGroupedByUser(): \Illuminate\Support\Collection
    {
        $soasWithAging = collect();

        // Get all active aging buckets
        $agingBuckets = SoaAging::getValues();

        foreach ($agingBuckets as $agingValue) {
            // Build query for this aging bucket
            $query = Soa::query()
                ->select('id', 'user_id', 'soa_number', 'account_code', 'due_date', 'amount', 'status')
                ->where('status', '!=', SoaStatus::PAID)
                ->orderBy('user_id')
                ->orderBy('due_date');

            // Apply the aging filter
            $this->applyAgingFilter($query, $agingValue);

            $results = $query->get();

            if ($results->isNotEmpty()) {
                foreach ($results as $soa) {
                    $key = $soa->user_id . '_' . $agingValue;
                    if (!$soasWithAging->has($key)) {
                        $soasWithAging->put($key, [
                            'user_id' => $soa->user_id,
                            'aging_value' => $agingValue,
                            'aging_label' => SoaAging::label($agingValue),
                            'soas' => collect(),
                        ]);
                    }
                    $soasWithAging->get($key)['soas']->push($soa);
                }
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
        $query->when($agingValue == SoaAging::PAST_DUE, function ($q) {
            $range = SoaAging::pastDueDayBucketsRange(SoaAging::PAST_DUE);
            $q->whereRaw('DATEDIFF(day, GETDATE(), due_date) < ?', [end($range) ?? 0]);
        })
        ->when($agingValue == SoaAging::DUE_WITHIN_30_DAYS, function ($q) {
            $range = SoaAging::pastDueDayBucketsRange(SoaAging::DUE_WITHIN_30_DAYS);
            $q->whereRaw('DATEDIFF(day, GETDATE(), due_date) BETWEEN ? AND ?', $range);
        })
        ->when($agingValue == SoaAging::DUE_WITHIN_60_DAYS, function ($q) {
            $range = SoaAging::pastDueDayBucketsRange(SoaAging::DUE_WITHIN_60_DAYS);
            $q->whereRaw('DATEDIFF(day, GETDATE(), due_date) BETWEEN ? AND ?', $range);
        })
        ->when($agingValue == SoaAging::DUE_WITHIN_90_DAYS, function ($q) {
            $range = SoaAging::pastDueDayBucketsRange(SoaAging::DUE_WITHIN_90_DAYS);
            $q->whereRaw('DATEDIFF(day, GETDATE(), due_date) BETWEEN ? AND ?', $range);
        })
        ->when($agingValue == SoaAging::DUE_WITHIN_120_DAYS, function ($q) {
            $range = SoaAging::pastDueDayBucketsRange(SoaAging::DUE_WITHIN_120_DAYS);
            $q->whereRaw('DATEDIFF(day, GETDATE(), due_date) BETWEEN ? AND ?', $range);
        })
        ->when($agingValue == SoaAging::DUE_WITHIN_MORE_THAN_120_DAYS, function ($q) {
            $range = SoaAging::pastDueDayBucketsRange(SoaAging::DUE_WITHIN_MORE_THAN_120_DAYS);
            $q->whereRaw('DATEDIFF(day, GETDATE(), due_date) > ?', [reset($range) ?? 0]);
        });
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
            if ($chunkCount >= self::CHUNK_SIZE) {
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
