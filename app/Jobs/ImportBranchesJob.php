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
     * Create a new job instance.
     */
    public function __construct($chunk)
    {
        $this->chunk = $chunk;
    }

    /**
     * Execute the job.
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
