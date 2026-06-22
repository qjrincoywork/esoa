<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Jobs\{ ImportAccountsJob, ImportBranchesJob, ImportMainAccountsJob };
use App\Services\SoaImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{ DB, Log };
use Inertia\Inertia;

class AdminController extends Controller
{
    protected $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function startImport()
    {
        try {
            $this->importMainAccounts();
            $this->importAccounts();
        } catch (\Exception $e) {
            Log::error('startImport Jobs failed: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            throw $e;
        }
    }

    public function importAccounts()
    {
        try {
            DB::connection('hms')
                ->table('Accounts as a')
                ->leftJoin('agent_table as ag', 'a.ac_agcode', '=', 'ag.agent_code')
                ->orderBy('a.ac_id')
                ->select(['a.*', 'ag.agent_id', 'ag.agent_code', 'ag.agent_name'])
                ->chunk(2000, function ($chunk) {
                    Log::info('Start Account: ' . $chunk->count());
                    ImportAccountsJob::dispatch($chunk);
                });

            Log::info('End Account Job');
        } catch (\Exception $e) {
            Log::error('Job failed Accounts: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            throw $e;
        }
    }

    public function importBranches()
    {
        try {
            DB::connection('hms')
                ->table('Branches as b')
                ->orderBy('b.br_id')
                ->select(['b.*'])
                ->chunk(2000, function ($chunk) {
                    Log::info('Start Branch: ' . $chunk->count());
                    ImportBranchesJob::dispatch($chunk);
                });

            Log::info('End Branch Job');
        } catch (\Exception $e) {
            Log::error('Job failed Branches: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            throw $e;
        }
    }

    public function importMainAccounts()
    {
        try {
            DB::connection('hms')
                ->table('MainAcct')
                ->orderBy('ma_id')
                ->chunk(2000, function ($chunk) {
                    Log::info('Start Main Account: ' . $chunk->count());
                    ImportMainAccountsJob::dispatch($chunk);
                });

            Log::info('End Main Account Job');
        } catch (\Exception $e) {
            Log::error('Job failed: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            throw $e;
        }
    }

    public function importSoa(Request $request, SoaImportService $soaImportService)
    {
        try {
            $limit = $request->filled('limit') ? $request->integer('limit') : null;
            $dispatched = $soaImportService->dispatchImport($limit);

            Log::info('End SOA Job', ['dispatched' => $dispatched]);

            return response()->json([
                'message' => 'SOA import jobs dispatched.',
                'dispatched' => $dispatched,
            ]);
        } catch (\Exception $e) {
            Log::error('Job failed: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            throw $e;
        }
    }

    public function index(Request $request)
    {
        return redirect()->route('dashboard');
    }

    public function create() {}

    public function store(Request $request) {}

    public function show(string $id) {}

    public function edit(string $id) {}

    public function update(Request $request, string $id) {}

    public function destroy(string $id) {}
}
