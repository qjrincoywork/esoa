<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\BillType;
use App\Enums\Server;
use App\Enums\SoaStatus;
use App\Enums\UntagType;
use App\Helpers\CommonHelper;
use App\Helpers\CustomResponse;
use App\Helpers\SqlDatabase;
use App\Http\Controllers\Controller;
use App\Http\Requests\Soa\{CreateRequest, FileProxyRequest, ListRequest, RecomputeTaxRequest, UpdateRequest, UpdateTagRequest };
use App\Http\Resources\AccountResource;
use App\Http\Resources\BranchResource;
use App\Http\Resources\CommonResource;
use App\Http\Resources\{BillingRefResource, OldSoaResource, SoaResource };
use App\Mail\NewSoaUploaded;
use App\Models\{Account, Citizenship, CivilStatus, Contact, Department, Gender, MainAccount, Position, Soa, Suffix, User };
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{ DB, Http, Mail, Storage };
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SoaController extends Controller
{
    /**
     * SqlDatabase instance.
     *
     * @var SqlDatabase
     */
    protected $sqlDatabase;

    /**
     * Constructor
     *
     * @param SqlDatabase $sqlDatabase
     *
     * @return void
     */
    public function __construct()
    {
        $this->sqlDatabase = SqlDatabase::class;
    }

    /**
     * Fetch a file from the given URL.
     *
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function fileProxy(FileProxyRequest $request)
    {
        $fileUrl = env('VC_ADMIN_DOMAIN') . $request->get('url');

        if (!$fileUrl) {
            return CustomResponse::error('URL parameter is required', Response::HTTP_BAD_REQUEST);
        }

        try {
            $response = Http::withoutVerifying()->timeout(15)->get($fileUrl);
            $contentType = $response->header('Content-Type') ?? 'application/octet-stream';

            if ($response->successful()) {
                return response($response->body(), Response::HTTP_OK, [
                    'Content-Type' => $contentType,
                    'Access-Control-Allow-Origin' => '*',
                    'Content-Disposition' => 'inline',
                ]);
            } else {
                return CustomResponse::error('Failed to fetch PDF', $response->status());
            }
        } catch (\Exception $e) {
            return CustomResponse::error('An error occurred: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(ListRequest $request)
    {
        $soas = (new $this->sqlDatabase(Server::SOA))->getSoas($request->validated());

        return Inertia::render('soas/Index', [
            'soas' => new CommonResource(OldSoaResource::collection($soas))
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function list(ListRequest $request)
    {
        $soas = (new Soa)->getSoas($request->validated());

        return Inertia::render('soas/List', [
            'soas' => new CommonResource(SoaResource::collection($soas))
        ]);
    }

    /**
     * Display a file listing of the resource.
     */
    public function fileList(Soa $soa, Request $request)
    {
        $billing = (new $this->sqlDatabase(Server::HMS))->getClaimByBillingRef($request->all());
        //http://192.170.11.185/dmis_finance/file/rm/ //EO-2832655-003
        // $files = Storage::disk('rm')->files('EO-3075098-001'); // 'files' is the sub-directory name
        $files = [];
        if (isset($billing->bl_claimnum)) {
            $files = Storage::disk('rm')->files($billing->bl_claimnum);
        }
        $files = Storage::disk('rm')->files('EO-2832655-003');

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'files' => $files,
            ]);
        }
    }

    public function previewFile(Request $request, $file = null)
    {
        $file = $file ?: $request->input('file');

        if (!$file) {
            abort(400, 'File path is required');
        }

        $disk = Storage::disk('rm');

        if (!$disk->exists($file)) {
            abort(404, 'File not found');
        }

        $stream = $disk->readStream($file);

        if (!is_resource($stream)) {
            abort(404, 'File is not readable');
        }

        $mimeType = 'application/octet-stream';
        try {
            $mimeType = $disk->mimeType($file);
        } catch (\Exception $e) {
            throw new \Exception('Unable to determine file MIME type: ' . $e->getMessage());
        }

        $fileName = basename($file);
        $fileSize = null;

        try {
            $fileSize = $disk->size($file);
        } catch (\Exception $e) {
            throw new \Exception('Unable to determine file size: ' . $e->getMessage());
        }

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            fclose($stream);
        }, Response::HTTP_OK, array_filter([
            'Content-Type' => $mimeType,
            'Content-Disposition' => sprintf('inline; filename="%s"', $fileName),
            'Content-Length' => $fileSize,
        ]));
    }

    public function create(Request $request)
    {
        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'account_types' => AccountType::list(),
                'bill_types' => BillType::list(),
                'status_types' => SoaStatus::list(),
            ]);
        }
    }

    public function store(CreateRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            (new Soa())->saveSoa($validated);

            // Commit transaction
            DB::commit();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::ok('Soa Created successfully', Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            // Catch and handle any unexpected errors
            DB::rollBack();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                // Catch and handle any unexpected errors
                return CustomResponse::error($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
    }

    public function getAccounts(Request $request)
    {
        $accounts = (new $this->sqlDatabase(Server::HMS))->getAccountsByParams($request->all());

        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'accounts' => new CommonResource(AccountResource::collection($accounts))
            ]);
        }
    }

    public function getBillingRefs(Request $request)
    {
        $billingRefs = (new $this->sqlDatabase(Server::HMS))->getBillingRefsByParams($request->all());

        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'billing_refs' => new CommonResource(BillingRefResource::collection($billingRefs))
            ]);
        }
    }

    public function getBranches(Request $request)
    {
        $branches = (new $this->sqlDatabase(Server::HMS))->getBranchesByParams($request->all());

        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'branches' => new CommonResource(BranchResource::collection($branches))
            ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        $soa = (new $this->sqlDatabase(Server::SOA))->getSoa($id);

        return Inertia::render('soas/Index', [
            'soa' => OldSoaResource::make($soa)
        ]);
    }

    /**
     * Edit the specified resource.
     */
    public function edit(Request $request, int $id)
    {
        $soa = (new $this->sqlDatabase(Server::SOA))->getSoa($id);

        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'soa' => OldSoaResource::make($soa)
            ]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRequest $request)
    {
        $validated = $request->validated();

        DB::connection(Server::SOA)->beginTransaction();

        try {
            (new $this->sqlDatabase(Server::SOA))->saveSoa($validated);

            // Commit transaction
            DB::connection(Server::SOA)->commit();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::ok('Soa Updated successfully', Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            // Catch and handle any unexpected errors
            DB::connection(Server::SOA)->rollBack();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                // Catch and handle any unexpected errors
                return CustomResponse::error($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
    }

    /**
     * Untag the specified soa from a user.
     */
    public function untag(Request $request)
    {
        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'untag_types' => UntagType::list(),
            ]);
        }
    }

    /**
     * Update the tag of the specified soa from a user.
     */
    public function updateTag(UpdateTagRequest $request)
    {
        $validated = $request->validated();
        DB::connection(Server::SOA)->beginTransaction();

        try {
            switch ($validated['untag_type']) {
                case UntagType::USER_ERROR:
                    $validated['reason'] = __('esoa.reason.user_error');
                    break;
                case UntagType::CLIENT_ERROR:
                    $validated['reason'] = __('esoa.reason.client_error');
                    break;
                case UntagType::BOUNCED_RETURNED_CHECK:
                    $validated['reason'] = __('esoa.reason.bounced_returned_check');
                    break;
            }
            $soa = (new $this->sqlDatabase(Server::SOA))->getSoa($validated['id']);

            if (!$soa) {
                throw new \Exception('SOA record not found.');
            }

            // (new $this->sqlDatabase(Server::SOA))->untagSoa($soa, $validated);
            Mail::to('quirjohnincoy.work@gmail.com')->send(new NewSoaUploaded($soa));

            // Commit transaction
            DB::connection(Server::SOA)->commit();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::ok('Retraction Completed', Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            // Catch and handle any unexpected errors
            DB::connection(Server::SOA)->rollBack();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                // Catch and handle any unexpected errors
                return CustomResponse::error($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
    }

    public function taxComputation(Request $request)
    {
        return Inertia::render('soas/TaxComputation');
    }

    public function recomputeTax(RecomputeTaxRequest $request)
    {
        $validated = $request->validated();
        // DB::connection(Server::HMS)->beginTransaction();

        try {
            // Commit transaction
            // DB::connection(Server::HMS)->commit();
            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::ok('Retraction Completed', Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            // Catch and handle any unexpected errors
            // DB::connection(Server::HMS)->rollBack();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                // Catch and handle any unexpected errors
                return CustomResponse::error($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
    }
}
