<?php

namespace App\Jobs;

use App\Enums\AccountType;
use App\Enums\BillType;
use App\Enums\SoaStatus;
use App\Models\Soa;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ImportUploadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;

    /**
     * Create the job with the legacy Upload rows to import.
     *
     * @param  mixed  $chunk   A Collection, array, or single legacy Upload row (up_* fields).
     * @param  int    $authId  User id recorded as the owner of the imported SOAs.
     */
    public function __construct(
        public mixed $chunk,
        public int $authId = 1,
    ) {}

    /**
     * Import each legacy Upload row into a Soa within a single transaction.
     *
     * Iterates the normalized records and delegates to importUpload(), then
     * commits. Any failure rolls back the transaction, logs the error, and
     * rethrows so the job can be retried.
     */
    public function handle(): void
    {
        DB::beginTransaction();

        try {
            $uploadsFolder = config('vc.uploads_folder');
            $billingDisk = Storage::disk(config('vc.billing_disk'));

            foreach ($this->records() as $upload) {
                $this->importUpload($upload, $uploadsFolder, $billingDisk);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('ImportUploadsJob failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Normalize the constructor payload into an iterable of Upload rows.
     *
     * A Collection or array is returned as-is; a single row is wrapped in an array.
     *
     * @return iterable<object>
     */
    protected function records(): iterable
    {
        if ($this->chunk instanceof Collection) {
            return $this->chunk;
        }

        if (is_array($this->chunk)) {
            return $this->chunk;
        }

        return [$this->chunk];
    }

    /**
     * Create a single Soa from a legacy Upload row.
     *
     * Skips the row when the account code or SOA number is empty, or when a Soa
     * with the same soa_number already exists. Copies any PDF/XLS source files
     * to the billing disk, derives the account type (TPA when the code starts
     * with "TP", otherwise HMO) and the status from up_status/up_endorsedtoacct
     * (UNPAID, ENDORSED, or PAID), then persists the Soa keeping the legacy
     * upload date as created_at.
     */
    protected function importUpload(object $upload, string $uploadsFolder, $billingDisk): void
    {
        if (empty($upload->up_accode) || empty($upload->up_soanum)) {
            return;
        }

        if (Soa::where('soa_number', $upload->up_soanum)->exists()) {
            return;
        }

        $directory = $this->storageDirectory($upload);
        $filePathPdf = ! empty($upload->up_filepdf)
            ? $this->copySourceFile($upload, $upload->up_filepdf, $uploadsFolder, $billingDisk, $directory)
            : null;
        $filePathXls = ! empty($upload->up_filexls)
            ? $this->copySourceFile($upload, $upload->up_filexls, $uploadsFolder, $billingDisk, $directory)
            : null;

        $soa = new Soa();

        if ($upload->up_status == 0 && $upload->up_endorsedtoacct == 0) {
            $status = SoaStatus::UNPAID;
        } else if ($upload->up_status == 0 && $upload->up_endorsedtoacct == 1) {
            $status = SoaStatus::ENDORSED;
        } else {
            $status = SoaStatus::PAID;
        }
        $soa->fill([
            'user_id' => $this->authId,
            'soa_number' => trim($upload->up_soanum), //$upload->up_soanum,
            'account_type' => str_starts_with($upload->up_accode, 'TP')
                ? AccountType::TPA
                : AccountType::HMO,
            'account_code' => $upload->up_accode,
            'branch_code' => ! empty($upload->up_branchcode) ? $upload->up_branchcode : null,
            'bill_type' => BillType::oldValue($upload->up_billtype),
            'status' => $status,
            'due_date' => $upload->up_due_date,
            'period_date_from' => $upload->up_poc_start,
            'period_date_to' => $upload->up_poc_end,
            'contract_date_from' => $upload->up_dp_from,
            'contract_date_to' => $upload->up_dp_to,
            'amount' => $upload->up_amount,
            'file_pdf' => $filePathPdf,
            'file_xls' => $filePathXls,
            'created_at' => $upload->up_date,
        ]);

        $soa->created_at = $upload->up_date;
        $soa->save();
    }

    /**
     * Build the billing-disk directory for an upload: the account code, with
     * "/branchCode" appended when a branch code is present.
     */
    protected function storageDirectory(object $upload): string
    {
        return $upload->up_accode
            . (! empty($upload->up_branchcode) ? '/'.$upload->up_branchcode : '');
    }

    /**
     * Copy one source file from the legacy uploads folder to the billing disk.
     *
     * The source path is built from the uploads folder plus the macode/accode
     * subfolders and filename. If the source file does not exist a warning is
     * logged and null is returned; otherwise the target directory is created,
     * the file is written, and the destination path is returned.
     *
     * @return string|null  The destination path on the billing disk, or null when the source is missing.
     */
    protected function copySourceFile(
        object $upload,
        string $filename,
        string $uploadsFolder,
        $billingDisk,
        string $directory,
    ): ?string {
        $sourceFile = $uploadsFolder.DIRECTORY_SEPARATOR
            .$upload->up_macode.DIRECTORY_SEPARATOR
            .$upload->up_accode.DIRECTORY_SEPARATOR
            .$filename;

        if (! is_file($sourceFile)) {
            Log::warning('SOA import source file not found', [
                'soa_number' => $upload->up_soanum ?? null,
                'source' => $sourceFile,
            ]);

            return null;
        }

        $billingDisk->makeDirectory($directory);
        $destination = $directory.'/'.$filename;
        $billingDisk->put($destination, file_get_contents($sourceFile));

        return $destination;
    }
}
