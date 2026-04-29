<?php

namespace App\Jobs;

use App\Enums\AccountType;
use App\Enums\BillType;
use App\Enums\SoaStatus;
use App\Models\Soa;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\{ DB, Http, Log, Storage };
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;

class ImportUploadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $chunk;
    public $timeout = 120;

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
            $authId = 1; // fallback if queue doesn't have auth
            Log::info('Start Logging');

            foreach ($this->chunk as $upload) {
                if (
                    !empty($upload->up_accode)
                    && !empty($upload->up_soanum)
                ) {
                    $directory = $upload->up_accode . (!empty($upload->up_branchcode) ? "/" . $upload->up_branchcode : "");
                    $filePathPDF = !empty($upload->up_filepdf) ? $directory . '/' . $upload->up_filepdf : null;
                    $filePathXls = !empty($upload->up_filexls) ? $directory . '/' . $upload->up_filexls : null;
                    if (!empty($upload->up_filepdf)) {
                        // https://valucarehealth.com/esoa/admin/soa/MA160323417/TP-08731604/BI-000051081.pdf
                        $filename = $upload->up_filepdf;

                        $url = 'https://valucarehealth.com/esoa/admin/soa/'
                            . $upload->up_macode . '/'
                            . $upload->up_accode . '/'
                            . $filename;

                        $url = str_replace(' ', '%20', $url);

                        $response = Http::withoutVerifying()
                            ->timeout(120)
                            ->connectTimeout(30)
                            ->retry(3, 2000)
                            ->get($url);

                        if (!$response->successful()) {
                            dd($url, $response->status(), $response->body());
                        }

                        $directory = $upload->up_accode
                            . (!empty($upload->up_branchcode) ? "/" . $upload->up_branchcode : "");

                        $disk = Storage::disk(env('BILLING_DISK'));
                        $disk->makeDirectory($directory);

                        $pdfFilePath = $directory . '/' . $filename;

                        $disk->put($pdfFilePath, $response->body());
                    }
                    if (!empty($upload->up_filexls)) {
                        $filename = $upload->up_filexls;

                        $url = 'https://valucarehealth.com/esoa/admin/soa/'
                            . $upload->up_macode . '/'
                            . $upload->up_accode . '/'
                            . $filename;

                        $url = str_replace(' ', '%20', $url);

                        $response = Http::withoutVerifying()
                            ->timeout(120)
                            ->connectTimeout(30)
                            ->retry(3, 2000)
                            ->get($url);

                        if (!$response->successful()) {
                            dd($url, $response->status(), $response->body());
                        }

                        $directory = $upload->up_accode
                            . (!empty($upload->up_branchcode) ? "/" . $upload->up_branchcode : "");

                        $disk = Storage::disk(env('BILLING_DISK'));
                        $disk->makeDirectory($directory);

                        $xlsFilePath = $directory . '/' . $filename;

                        $disk->put($xlsFilePath, $response->body());
                    }

                    // (new Soa)->fill([
                    //     'user_id' => $authId,
                    //     'soa_number' => $upload->up_soanum,
                    //     'account_type' => AccountType::TPA_HMO,
                    //     'account_code' => $upload->up_accode,
                    //     'branch_code' => $upload->up_branchcode ?? null,
                    //     // 'billing_ref' => $upload->up_refid,
                    //     'bill_type' => BillType::oldValue($upload->up_billtype),
                    //     'status' => SoaStatus::UNPAID,
                    //     'due_date' => $upload->up_due_date,
                    //     'period_date_from' => $upload->up_poc_start,
                    //     'period_date_to' => $upload->up_poc_end,
                    //     'amount' => $upload->up_amount,
                    //     'file_pdf' => $filePathPDF,
                    //     'file_xls' => $filePathXls,
                    //     'created_at' => $upload->up_date,
                    // ])
                    // ->save();
                    $soa = new Soa();

                    $soa->fill([
                        'user_id' => $authId,
                        'soa_number' => $upload->up_soanum,
                        'account_type' => AccountType::TPA_HMO,
                        'account_code' => $upload->up_accode,
                        'branch_code' => $upload->up_branchcode ?? null,
                        // 'billing_ref' => $upload->up_refid,
                        'bill_type' => BillType::oldValue($upload->up_billtype),
                        'status' => SoaStatus::UNPAID,
                        'due_date' => $upload->up_due_date,
                        'period_date_from' => $upload->up_poc_start,
                        'period_date_to' => $upload->up_poc_end,
                        'amount' => $upload->up_amount,
                        'file_pdf' => $filePathPDF,
                        'file_xls' => $filePathXls,
                        'created_at' => $upload->up_date,
                    ]);

                    $soa->created_at = $upload->up_date;
                    $soa->save();
                }
            }
            DB::commit();
            Log::info('End Logging');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Job failed: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
            // throw $e;
            return; // skip instead of fail
        }
    }
}
