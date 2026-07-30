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
    /**
     * User model instance.
     *
     * @var User
     */
    protected $user;

    /**
     * Inject the User model instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Run the full HMS reference-data import: main accounts then accounts.
     *
     * Invoked as the entry point for the import pipeline; logs and re-throws
     * any exception so the caller (console command / queue) sees the failure.
     *
     * @return void
     * @throws \Exception
     */
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

    /**
     * Import HMS accounts (joined with agent data) in chunks of 2,000.
     *
     * Streams the HMS `Accounts`/`agent_table` join and dispatches an
     * {@see ImportAccountsJob} per chunk. Logs and re-throws on failure.
     *
     * @return void
     * @throws \Exception
     */
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

    /**
     * Import HMS branches in chunks of 2,000.
     *
     * Streams the HMS `Branches` table and dispatches an
     * {@see ImportBranchesJob} per chunk. Logs and re-throws on failure.
     *
     * @return void
     * @throws \Exception
     */
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

    /**
     * Import HMS main accounts in chunks of 2,000.
     *
     * Streams the HMS `MainAcct` table and dispatches an
     * {@see ImportMainAccountsJob} per chunk. Logs and re-throws on failure.
     *
     * @return void
     * @throws \Exception
     */
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

    /**
     * Dispatch SOA import jobs via {@see SoaImportService}, optionally capped.
     *
     * Reads an optional `limit` query param to bound how many SOAs are queued,
     * delegates dispatching to the service, and returns a JSON summary with the
     * dispatched count. Logs and re-throws on failure.
     *
     * @param Request $request
     * @param SoaImportService $soaImportService
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
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

    /**
     * Redirect the admin landing route to the dashboard.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function index(Request $request)
    {
        return redirect()->route('dashboard');
    }

    /**
     * Unused resource stub; no create form is served by this controller.
     */
    public function create() {}

    /**
     * Unused resource stub; this controller does not persist records directly.
     */
    public function store(Request $request) {}

    /**
     * Unused resource stub; no single-record view is served by this controller.
     */
    public function show(string $id) {}

    /**
     * Unused resource stub; no edit form is served by this controller.
     */
    public function edit(string $id) {}

    /**
     * Unused resource stub; this controller does not update records directly.
     */
    public function update(Request $request, string $id) {}

    /**
     * Unused resource stub; this controller does not delete records directly.
     */
    public function destroy(string $id) {}
}
