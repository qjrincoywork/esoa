<?php

namespace App\Jobs;


use App\Models\Branch;
// use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\{ DB, Log };
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportBranchesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $chunk;

    /**
     * Create the job with the chunk of legacy branch rows to import.
     *
     * @param  iterable  $chunk  Legacy branch records (objects exposing br_* fields).
     */
    public function __construct($chunk)
    {
        $this->chunk = $chunk;
    }

    /**
     * Import the chunked legacy branches into the branches table.
     *
     * Each row is mapped from its br_* columns and inserted via Branch::create
     * (integration defaults to 0 when missing). The chunk runs in one
     * transaction that rolls back and rethrows on any failure.
     */
    public function handle(): void
    {
        DB::beginTransaction();
        try {
            Log::info('Start Branches Logging');
            foreach ($this->chunk as $branch) {
                // branches table
                Branch::create([
                    'code' => $branch->br_code,
                    'name' => $branch->br_branch_name,
                    'address' => $branch->br_address,
                    'attention' => $branch->br_attention,
                    'position' => $branch->br_position,
                    'cm_code' => $branch->br_cm_code,
                    'ac_code' => $branch->br_ac_code,
                    'integration' => $branch->br_integration ?? 0,
                    'tin' => $branch->br_tin,
                    'disclaimer' => $branch->br_disclaimer,
                ]);
            }
            DB::commit();
            Log::info('End Branches Logging');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Job failed: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
            throw $e;
        }
    }
}
