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

    public function __construct(
        public mixed $chunk,
        public int $authId = 1,
    ) {}

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
        $soa->fill([
            'user_id' => $this->authId,
            'soa_number' => $upload->up_soanum,
            'account_type' => str_starts_with($upload->up_accode, 'TP')
                ? AccountType::TPA
                : AccountType::HMO,
            'account_code' => $upload->up_accode,
            'branch_code' => ! empty($upload->up_branchcode) ? $upload->up_branchcode : null,
            'bill_type' => BillType::oldValue($upload->up_billtype),
            'status' => ! empty($upload->up_endorsedtoacct)
                ? SoaStatus::ENDORSED
                : SoaStatus::UNPAID,
            'due_date' => $upload->up_due_date,
            'period_date_from' => $upload->up_poc_start,
            'period_date_to' => $upload->up_poc_end,
            'amount' => $upload->up_amount,
            'file_pdf' => $filePathPdf,
            'file_xls' => $filePathXls,
            'created_at' => $upload->up_date,
        ]);

        $soa->created_at = $upload->up_date;
        $soa->save();
    }

    protected function storageDirectory(object $upload): string
    {
        return $upload->up_accode
            . (! empty($upload->up_branchcode) ? '/'.$upload->up_branchcode : '');
    }

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
