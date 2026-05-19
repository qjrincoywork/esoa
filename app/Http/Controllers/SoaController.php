<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\BillType;
use App\Enums\Server;
use App\Enums\SoaAging;
use App\Enums\SoaAmountOperation;
use App\Enums\SoaStatus;
use App\Enums\UntagType;
use App\Helpers\CommonHelper;
use App\Helpers\CustomResponse;
use App\Helpers\SqlDatabase;
use App\Http\Controllers\Controller;
use App\Http\Requests\Soa\{AccountBranchMembersRequest, AdjustAmountRequest, CreateRequest, FileListRequest, FileProxyRequest, ListRequest, RecordViewedRequest, RecomputeTaxRequest, UpdateRequest, UpdateTagRequest };
use App\Http\Resources\AccountResource;
use App\Http\Resources\BranchResource;
use App\Http\Resources\CommonResource;
use App\Http\Resources\{AccountBranchMemberResource, BillingRefResource, OldSoaResource, SoaActivityListResource, SoaAgingCountResource, SoaResource };
use App\Mail\{ BillingInvoiceStatusChanged, NewBillingInvoiceUploaded, NewSoaUploaded };
use App\Models\{Account, Citizenship, CivilStatus, Contact, Department, Gender, MainAccount, Position, Soa, Suffix, UserDetail};
use Carbon\Carbon;
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
     * Soa model instance.
     *
     * @var Soa
     */
    protected $soa;

    /**
     * Constructor
     *
     * @param SqlDatabase $sqlDatabase
     * @param Soa $soa
     * @return void
     */
    public function __construct()
    {
        $this->sqlDatabase = SqlDatabase::class;
        $this->soa = new Soa();
    }

    /**
     * Ensure the authenticated user may modify the given Eloquent SOA (same scope as list filters).
     */
    protected function assertUserMayAccessModelSoa(Soa $soa): void
    {
        $authUser = auth()->user();
        if (!$authUser) {
            abort(Response::HTTP_UNAUTHORIZED);
        }
        if ($authUser->hasRole('superadmin')) {
            return;
        }
        $detail = $authUser->userDetail;
        if ($detail && isset($detail->account_code) && $soa->account_code !== $detail->account_code) {
            abort(Response::HTTP_FORBIDDEN);
        }
        if ($detail && isset($detail->branch_code) && $soa->branch_code !== null && $soa->branch_code !== '' && $soa->branch_code !== $detail->branch_code) {
            abort(Response::HTTP_FORBIDDEN);
        }
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
     * Display the dashboard.
     */
    public function dashboard(Request $request)
    {
        $soaAgingCounts = $this->soa->agingCountsPastDue();

        return Inertia::render('soas/Dashboard', [
            'soa_agings' => SoaAgingCountResource::collection($soaAgingCounts),
        ]);
    }

    /**
     * Display a listing of the account / branch members.
     */
    public function accountBranchMembers(AccountBranchMembersRequest $request, string $account_code, string $branch_code)
    {
        $validated = $request->validated();
        $members = (new $this->sqlDatabase(Server::HMS))->getCardHolderDetailsByParams($validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'members' => new CommonResource(AccountBranchMemberResource::collection($members)),
            ]);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function list(ListRequest $request)
    {
        $soas = $this->soa->getSoas($request->validated());

        // Return JSON for AJAX/fetch requests (for SearchableCombobox usage)
        if ($request->wantsJson()) {
            return response()->json([
                'data' => SoaResource::collection($soas)->resolve(),
                'current_page' => $soas->currentPage(),
                'last_page' => $soas->lastPage(),
                'total' => $soas->total(),
                'per_page' => $soas->perPage(),
            ]);
        }

        return Inertia::render('soas/List', [
            'soas' => new CommonResource(SoaResource::collection($soas)),
            'soa_status_options' => SoaStatus::list(),
            'soa_account_type_options' => AccountType::list(),
        ]);
    }

    /**
     * Display a file listing of the resource.
     */
    public function fileList(FileListRequest $request)
    {
        $validated = $request->validated();
        $billing = (new $this->sqlDatabase(Server::HMS))->getBillingByParams($validated);
        //http://192.170.11.185/dmis_finance/file/rm/ //EO-2832655-003, EO-3085829-004
        $files = [];
        if (!empty($billing) && !empty($billing->bl_claimnum)) {
            $paths = Storage::disk(env('RM_DISK', 'public'))->files($billing->bl_claimnum);
            $userId = (int) auth()->id();
            $files = array_map(function (string $path) use ($userId) {
                return [
                    'name' => basename($path),
                    'preview_token' => CommonHelper::createFilePreviewToken(
                        env('RM_DISK', 'public'),
                        $path,
                        $userId
                    ),
                ];
            }, $paths);
        }
        // $files = Storage::disk(env('RM_DISK', 'public'))->files('EO-3024023-001'); // 'files' is the sub-directory name
        // $files = Storage::disk(env('RM_DISK', 'public'))->files('EO-2832655-003');
        // $files = Storage::disk(env('RM_DISK', 'public'))->files('EO-3082257-029');

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'files' => $files,
            ]);
        }
    }

    public function previewFile(Request $request)
    {
        return CommonHelper::previewStoredFileFromToken(
            (string) $request->query('token', ''),
            env('RM_DISK', 'public'),
            $request->user()?->id
        );
    }

    /**
     * Stream a PDF or Excel file stored on the billing disk for this SOA.
     */
    public function streamBillingAttachment(int $id, string $type)
    {
        $soa = $this->soa->findOrFail($id);
        $this->recordBillingInvoiceViewedIfEligible($soa, request()->user());
        $this->assertUserMayAccessModelSoa($soa);

        $path = match ($type) {
            'pdf' => $soa->file_pdf,
            'excel' => $soa->file_xls,
            default => null,
        };

        if ($path === null || $path === '') {
            abort(Response::HTTP_NOT_FOUND);
        }

        $disk = Storage::disk(env('BILLING_DISK', 'public'));

        if (! $disk->exists($path)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $stream = $disk->readStream($path);

        if (! is_resource($stream)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $mimeType = 'application/octet-stream';
        try {
            $mimeType = $disk->mimeType($path);
        } catch (\Exception $e) {
            // keep default
        }

        $fileName = basename($path);
        $fileSize = null;
        try {
            $fileSize = $disk->size($path);
        } catch (\Exception $e) {
            // omit Content-Length
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

        try {
            $soa = DB::transaction(function () use ($validated, $request) {
                $soaNumber = $validated['soa_number'];
                CommonHelper::storeUploadedFiles(
                    $soaNumber,
                    $validated['account_code'],
                    $validated['branch_code'] ?? null,
                    $request,
                    $validated
                );

                $soa = $this->soa->saveSoa($validated);

                if (CommonHelper::hasFileAttachmentAndAccount($soa, $request)) {
                    CommonHelper::sendBillingInvoiceEmail($soa, $request->user(), NewBillingInvoiceUploaded::class);
                }

                return $soa;
            });

            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::ok('Soa Created successfully', Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            if ($request->wantsJson() || $request->ajax()) {
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
        $billingRefs = (new $this->sqlDatabase(Server::HMS))->getClaimDetailsByParams($request->all());
        // $billingRefs = (new $this->sqlDatabase(Server::HMS))->getBillingRefsByParams($request->all());

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
     * Add to or deduct from the Eloquent SOA amount (List / right-pane context).
     * Writes a {@see SoaActivity} row for the audit trail.
     */
    public function adjustAmount(AdjustAmountRequest $request)
    {
        $validated = $request->validated();
        $soa = $this->soa->findOrFail($validated['soa_id']);

        $this->assertUserMayAccessModelSoa($soa);

        $current = (float) $soa->amount;
        $delta = (float) $validated['amount'];
        $operation = $validated['operation'];
        $new = $operation === SoaAmountOperation::ADD
            ? round($current + $delta, 2)
            : round($current - $delta, 2);

        $soa->runInTransactionWithActivity(
            function (Soa $soa) use ($new) {
                $soa->amount = $new;
                $soa->save();
            },
            SoaAmountOperation::activityEvent($operation),
            [
                'from' => ['amount' => $current],
                'to' => [
                    'amount' => $new,
                    'operation' => $operation,
                    'operation_label' => SoaAmountOperation::label($operation),
                    'delta' => $delta,
                ],
            ],
            $request->user()
        );

        $soa->refresh();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Amount updated.',
                'amount' => number_format((float) $soa->amount, 2),
                'amount_raw' => (float) $soa->amount,
            ], Response::HTTP_OK);
        }

        return back();
    }

    /**
     * Display SOA activities for the given SOA id.
     */
    public function activities(Request $request, int $id)
    {
        $soa = $this->soa->findOrFail($id);

        $perPage = (int) $request->get('per_page', config('vc.default_pages'));
        $paginator = $soa->soaActivity()
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $payload = [
            'data' => SoaActivityListResource::collection($paginator->items())->resolve($request),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'activities' => $payload,
            ]);
        }

        return response()->json(['activities' => $payload]);
    }

    /**
     * Record a single "billing invoice viewed" activity (account_branch_admin only).
     * Ensures only one activity per SOA for this event.
     */
    public function recordViewed(RecordViewedRequest $request, int $id)
    {
        $soa = $this->soa->findOrFail($id);
        $this->recordBillingInvoiceViewedIfEligible($soa, $request->user());

        return response()->noContent();
    }

    /**
     * Record viewed activity from email link, then redirect to PDF attachment.
     */
    public function viewBillingInvoice(RecordViewedRequest $request, int $id)
    {
        $soa = $this->soa->findOrFail($id);
        $this->recordBillingInvoiceViewedIfEligible($soa, $request->user());

        return redirect()->route('soas.billing_attachments', [
            'id' => $soa->id,
            'type' => 'pdf',
        ]);
    }

    /**
     * Persist a single billing-invoice viewed activity for account_branch_admin users.
     */
    private function recordBillingInvoiceViewedIfEligible(Soa $soa, $user): void
    {
        if (!$user || !$user->hasRole('account_branch_admin')) {
            return;
        }

        $event = 'billing_invoice_viewed';
        $alreadyRecorded = $soa->soaActivity()
            ->where('event', $event)
            ->exists();

        if ($alreadyRecorded) {
            return;
        }

        $soa->recordActivity($event, [
            'to' => [
                'soa_number' => $soa->soa_number,
                'message' => 'Billing invoice is viewed',
            ],
        ], $user);
    }

    /**
     * Edit the specified resource.
     */
    public function edit(Request $request, int $id)
    {
        $soa = $this->soa->findOrFail($id);
        $this->assertUserMayAccessModelSoa($soa);

        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'soa' => $soa,
                'account_types' => AccountType::list(),
                'bill_types' => BillType::list(),
                'status_types' => SoaStatus::list(),
            ]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRequest $request)
    {
        $validated = $request->validated();
        DB::beginTransaction();

        try {
            $soa = DB::transaction(function () use ($validated, $request) {
                $soa = $this->soa->findOrFail($validated['id']);

                CommonHelper::validateNotPaid($soa, SoaStatus::PAID);

                $soaNumber = $validated['soa_number'] ?? $soa->soa_number;
                $isAccountBranchAdmin = empty(auth()->user()->userDetail->employee_no);
                if (!$isAccountBranchAdmin) {
                    CommonHelper::storeUploadedFiles(
                        $soaNumber,
                        $validated['account_code'],
                        $validated['branch_code'] ?? null,
                        $request,
                        $validated
                    );
                }

                $original = CommonHelper::getFilteredOriginal($soa);
                $soa->update($validated);

                $changes = CommonHelper::getFilteredChanges($soa);
                $specifiedOriginal = collect($original)->only(array_keys($changes))->toArray();

                if (!empty($changes)) {
                    $soa->recordActivity('update', [
                        'from' => $specifiedOriginal,
                        'to' => $changes,
                    ], $request->user());

                    $soa->refresh();

                    if ($request->hasFile('file_pdf') && $soa->status == SoaStatus::UNPAID) {
                        if (CommonHelper::hasFileAttachmentAndAccount($soa, $request)) {
                            CommonHelper::sendBillingInvoiceEmail($soa, $request->user(), NewBillingInvoiceUploaded::class);
                        }
                    }

                    $statusChangedTo = $changes['status'] ?? null;
                    if (
                        in_array($statusChangedTo, config('vc.allowed_soa_status_for_account_branch_admin'))
                        && $request->user()->hasRole('account_branch_admin')
                    ) {
                        CommonHelper::sendBillingInvoiceEmail($soa, $request->user(), BillingInvoiceStatusChanged::class);
                    }
                }

                return $soa;
            });
            DB::commit();

            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::ok('Soa Updated successfully', Response::HTTP_OK);
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($request->wantsJson() || $request->ajax()) {
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
