<?php

namespace App\Services;

use App\Jobs\ImportUploadsJob;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SoaImportService
{
    /**
     * Queue SOA upload records for migration.
     */
    public function dispatchImport(?int $limit = null): int
    {
        $config = config('vc.soa_import');
        $chunkSize = $config['chunk_size'];
        $limit = $limit ?? $config['limit'];
        $dispatched = 0;

        $this->buildUploadQuery($config)
            ->orderBy('up_date')
            ->chunk($chunkSize, function (Collection $chunk) use (&$dispatched, $limit) {
                if ($limit !== null && $dispatched >= $limit) {
                    Log::info('SOA import limit reached', ['limit' => $limit]);

                    return false;
                }

                if ($limit !== null) {
                    $remaining = $limit - $dispatched;
                    if ($remaining <= 0) {
                        return false;
                    }

                    if ($chunk->count() > $remaining) {
                        $chunk = $chunk->take($remaining);
                    }
                }

                ImportUploadsJob::dispatch($chunk);
                $dispatched += $chunk->count();

                Log::info('SOA import chunk dispatched', [
                    'chunk' => $chunk->count(),
                    'total' => $dispatched,
                ]);
            });

        Log::info('SOA import dispatch completed', ['total' => $dispatched]);

        return $dispatched;
    }

    protected function buildUploadQuery(array $config): Builder
    {
        return DB::connection('soa')
            ->table('Upload')
            ->select([
                'up_id',
                'up_soanum',
                'up_macode',
                'up_branchcode',
                'up_accode',
                'up_amount',
                'up_filepdf',
                'up_filexls',
                'up_billtype',
                'up_date',
                'up_due_date',
                'up_poc_start',
                'up_poc_end',
                'up_status',
                'up_status_date',
                'up_endorsedtoacct',
            ])
            ->whereNull('up_delete_date')
            ->where('up_status', $config['status'])
            ->whereNotNull('up_macode')
            ->whereRaw("LTRIM(RTRIM([up_macode])) <> ''")
            ->where(function (Builder $query) use ($config) {
                $query->whereBetween('up_date', [$config['date_from'], $config['date_to']])
                    ->where('up_poc_start', '>=', $config['poc_start_from']);
            });
    }
}
