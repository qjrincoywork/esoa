<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\BillRefFrom;
use App\Enums\BillType;
use App\Enums\Server;
use App\Enums\SoaAmountOperation;
use App\Enums\SoaStatus;
use App\Exports\SoaBillingInvoiceExporter;
use App\Helpers\CommonHelper;
use App\Helpers\CustomResponse;
use App\Helpers\SqlDatabase;
use App\Http\Controllers\Controller;
use App\Http\Requests\Soa\{AccountBranchMembersRequest, AdjustAmountRequest, BillRefsRequest, CreateRequest, DestroyRequest, FileListRequest, FileProxyRequest, FindMemberRequest, ListRequest, MemberFilesRequest, RecordViewedRequest, RecomputeTaxRequest, UpdateRequest, UpdateTagRequest };
use App\Http\Resources\AccountResource;
use App\Http\Resources\BranchResource;
use App\Http\Resources\CommonResource;
use App\Http\Resources\{AccountBranchMemberResource, AccountPaymentResource, BillingRefResource, ConcernResource, MemberResource, OldSoaResource, SoaActivityListResource, SoaAgingCountResource, SoaResource };
use App\Mail\{ BillingInvoiceStatusChanged, NewBillingInvoiceUploaded };
use App\Models\Soa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{ DB, Http, Storage };
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
     * Fetch a file from the given URL.
     *
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function fileProxy(FileProxyRequest $request)
    {
        CommonHelper::assertUserMayAccessModel($request);
        $path = $request->get('url', '');

        // Fully decode first so that single- and double-encoded traversal
        // (e.g. %2e%2e, %252e%252e) cannot slip past the literal ".." check.
        $decodedPath = $path;
        for ($i = 0; $i < 3; $i++) {
            $decodedPath = rawurldecode($decodedPath);
        }

        // Only relative paths allowed: block absolute URLs, traversal, encoded
        // dots/percent, and null bytes on both the raw and fully-decoded value.
        if (
            preg_match('#^https?://#i', $path)
            || str_contains($path, '..')
            || str_contains($decodedPath, '..')
            || stripos($path, '%2e') !== false
            || stripos($path, '%25') !== false
            || str_contains($path, "\0")
            || str_contains($decodedPath, "\0")
        ) {
            return CustomResponse::error('Invalid URL', Response::HTTP_BAD_REQUEST);
        }

        $allowedBase = rtrim(config('vc.admin_domain'), '/');

        if (empty($allowedBase)) {
            return CustomResponse::error('Proxy not configured', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $fileUrl = $allowedBase . '/' . ltrim($path, '/');

        // Only render known-safe document/image types; never trust the upstream
        // content-type verbatim, and never render inline (prevents HTML/SVG XSS
        // being served from this application's origin).
        $allowedTypes = [
            'application/pdf',
            'image/png',
            'image/jpeg',
            'image/gif',
            'image/webp',
        ];

        try {
            // TLS verification enabled — withoutVerifying() removed
            $response = Http::timeout(15)->get($fileUrl);

            if ($response->successful()) {
                $upstreamType = strtolower(trim(explode(';', (string) ($response->header('Content-Type') ?? ''))[0]));
                $contentType = in_array($upstreamType, $allowedTypes, true)
                    ? $upstreamType
                    : 'application/octet-stream';

                return response($response->body(), Response::HTTP_OK, [
                    'Content-Type' => $contentType,
                    'Content-Disposition' => 'attachment',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            }

            return CustomResponse::error('Failed to fetch file', $response->status());
        } catch (\Exception $e) {
            return CustomResponse::serverError($e, 'SoaController::fileProxy');
        }
    }

    /**
     * Render the SOA dashboard with past-due aging counts.
     *
     * Loads the past-due aging bucket counts and passes them to the Inertia
     * "soas/Dashboard" page as an {@see SoaAgingCountResource} collection.
     *
     * @param Request $request
     * @return \Inertia\Response
     */
    public function dashboard(Request $request)
    {
        $soaAgingCounts = $this->soa->agingCountsPastDue();

        return Inertia::render('soas/Dashboard', [
            'soa_agings' => SoaAgingCountResource::collection($soaAgingCounts),
        ]);
    }

    /**
     * Return HMS card-holder members for an account/branch as JSON (AJAX only).
     *
     * Queries the HMS server via {@see SqlDatabase} using the validated params
     * and responds with an {@see AccountBranchMemberResource} collection. Non-AJAX
     * requests fall through and receive no content. Input is validated by
     * {@see AccountBranchMembersRequest}.
     *
     * @param AccountBranchMembersRequest $request
     * @param string $account_code
     * @param string $branch_code
     * @return \Illuminate\Http\JsonResponse|void
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
     * List SOAs (billing invoices), returning JSON for fetch calls or an Inertia page.
     *
     * For AJAX/fetch requests (e.g. the SearchableCombobox) it returns a paginated
     * JSON payload of {@see SoaResource} data with pagination metadata; otherwise
     * it renders the Inertia "soas/List" page with the collection plus status,
     * account-type and bill-type option lists. Filters are validated by
     * {@see ListRequest}.
     *
     * @param ListRequest $request
     * @return \Illuminate\Http\JsonResponse|\Inertia\Response
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
            'soa_bill_type_options' => BillType::list(),
        ]);
    }

    /**
     * Export billing invoices matching list filters as an Excel (.xls) file.
     */
    public function exportList(ListRequest $request)
    {
        $params = $request->validated();
        $query = $this->soa->listQuery($params);
        $maxRows = (int) config('vc.soa_export_max_rows', 7000);
        $total = $query->count();

        if ($total > $maxRows) {
            return CustomResponse::error(
                "Too many rows to export ({$total}). Please narrow your filters (maximum {$maxRows}).",
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if ($total === 0) {
            return CustomResponse::error(
                'No billing invoices match the selected filters.',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $filename = 'billing_invoices_' . now()->format('Y-m-d_His') . '.xls';

        return (new SoaBillingInvoiceExporter())->download($query, $filename);
    }

    /**
     * List RM-disk attachment files for a claim number as JSON (AJAX only).
     *
     * When a `claimnum` is provided, enumerates files on the RM disk under that
     * claim and returns each file's name together with a signed preview token
     * (issued by {@see CommonHelper::createFilePreviewToken()}) for later inline
     * streaming via previewFile(). Non-AJAX requests fall through and receive no
     * content. Input is validated by {@see FileListRequest}.
     *
     * Access control (RBAC): {@see CommonHelper::assertUserMayAccessModel()} is
     * enforced before any files are listed.
     *
     * @param FileListRequest $request
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function fileList(FileListRequest $request)
    {
        $validated = $request->validated();
        $files = [];
        if (isset($validated['claimnum']) && !empty($validated['claimnum'])) {
            // Confine RM document access to claims the caller's account owns (F-02).
            if (!(new $this->sqlDatabase(Server::HMS))->userCanAccessClaim($validated['claimnum'])) {
                abort(Response::HTTP_FORBIDDEN);
            }
            $paths = Storage::disk(config('vc.disks.rm'))->files($validated['claimnum']);
            $userId = (int) auth()->id();
            $files = array_map(function (string $path) use ($userId) {
                return [
                    'name' => basename($path),
                    'preview_token' => CommonHelper::createFilePreviewToken(
                        config('vc.disks.rm'),
                        $path,
                        $userId
                    ),
                ];
            }, $paths);
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'files' => $files,
            ]);
        }
    }

    /**
     * Stream a previously tokenized stored file inline (RM disk).
     *
     * The signed preview token — issued by {@see CommonHelper::createFilePreviewToken()}
     * in fileList()/memberFiles() — carries the disk, path and issuing user id, so
     * authorization is enforced by the token itself rather than a role check here.
     *
     * Access control (RBAC): route-level Spatie role/permission middleware guards
     * the endpoint; the token binds the file to the user it was issued for.
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function previewFile(Request $request)
    {
        return CommonHelper::previewStoredFileFromToken(
            (string) $request->query('token', ''),
            config('vc.disks.rm'),
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
        CommonHelper::assertUserMayAccessModel(request(), $soa);

        $path = match ($type) {
            'pdf' => $soa->file_pdf,
            'excel' => $soa->file_xls,
            default => null,
        };

        if ($path === null || $path === '') {
            abort(Response::HTTP_NOT_FOUND);
        }

        $disk = Storage::disk(config('vc.disks.billing'));

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

    /**
     * Return the lookup lists required to build the "Create SOA" form.
     *
     * Responds only to AJAX/JSON requests so the form's select options
     * (account types, bill types, status types and billing-reference sources)
     * can be fetched without a full page navigation. Non-AJAX requests fall
     * through and receive no content.
     *
     * Access control (RBAC): this endpoint is gated by Spatie role/permission
     * middleware registered on the route, so only users authorized to create
     * an SOA can reach it; no per-model ownership check is needed here because
     * the response contains reference data only.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void JSON of lookup lists for AJAX requests; void otherwise.
     */
    public function create(Request $request)
    {
        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'account_types' => AccountType::list(),
                'bill_types' => BillType::list(),
                'status_types' => SoaStatus::list(),
                'billing_ref_from_types' => BillRefFrom::list(),
            ]);
        }
    }

    /**
     * Persist a new SOA together with its uploaded attachments.
     *
     * Runs inside a DB transaction: stores any uploaded files, saves the SOA,
     * and — when the SOA has an attachment and an associated account — emails a
     * {@see NewBillingInvoiceUploaded} notification. Responds with a JSON success
     * envelope for AJAX requests, or a server-error envelope on failure.
     *
     * Access control (RBAC): route-level Spatie role/permission middleware
     * restricts this endpoint to users authorized to create an SOA; input is
     * validated and authorized by {@see CreateRequest}.
     *
     * @param CreateRequest $request
     * @return \Illuminate\Http\JsonResponse|void
     */
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
                return CustomResponse::serverError($e, 'SoaController');
            }
        }
    }

    /**
     * Return accounts from HMS matching the given search params (AJAX only).
     *
     * Feeds account-picker comboboxes on the SOA forms. Queries the HMS server
     * via {@see SqlDatabase} and responds with a JSON collection; non-AJAX
     * requests fall through and receive no content.
     *
     * Access control (RBAC): route-level Spatie role/permission middleware gates
     * the endpoint.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function getAccounts(Request $request)
    {
        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            $accounts = (new $this->sqlDatabase(Server::HMS))->getAccountsByParams($request->all());

            return response()->json([
                'accounts' => new CommonResource(AccountResource::collection($accounts))
            ]);
        }
    }

    /**
     * Return billing references from HMS matching the given params (AJAX only).
     *
     * Feeds the billing-reference picker on the SOA forms. Queries the HMS server
     * via {@see SqlDatabase} using validated params and responds with a JSON
     * collection; non-AJAX requests fall through and receive no content.
     *
     * Access control (RBAC): route-level Spatie role/permission middleware gates
     * the endpoint; input is validated by {@see BillRefsRequest}.
     *
     * @param BillRefsRequest $request
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function getBillingRefs(BillRefsRequest $request)
    {
        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            $billingRefs = (new $this->sqlDatabase(Server::HMS))->getBillingRefsByParams($request->validated());

            return response()->json([
                'billing_refs' => new CommonResource(BillingRefResource::collection($billingRefs))
            ]);
        }
    }

    /**
     * Return branches from HMS matching the given search params (AJAX only).
     *
     * Feeds branch-picker comboboxes on the SOA forms (typically scoped to a
     * selected account). Queries the HMS server via {@see SqlDatabase} and
     * responds with a JSON collection; non-AJAX requests fall through and
     * receive no content.
     *
     * Access control (RBAC): route-level Spatie role/permission middleware gates
     * the endpoint.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function getBranches(Request $request)
    {
        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            $branches = (new $this->sqlDatabase(Server::HMS))->getBranchesByParams($request->all());

            return response()->json([
                'branches' => new CommonResource(BranchResource::collection($branches))
            ]);
        }
    }

    /**
     * Add to or deduct from the Eloquent SOA amount (List / right-pane context).
     * Writes a {@see SoaActivity} row for the audit trail.
     */
    public function adjustAmount(AdjustAmountRequest $request)
    {
        $validated = $request->validated();
        $soa = $this->soa->findOrFail($validated['soa_id']);

        CommonHelper::assertUserMayAccessModel($request, $soa);

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
     * Return a paginated audit trail of activities for the given SOA as JSON.
     *
     * Resolves the SOA, paginates its activity rows (newest first) and returns
     * them as {@see SoaActivityListResource} data with pagination metadata. Always
     * responds with JSON regardless of request type.
     *
     * Access control (RBAC): {@see CommonHelper::assertUserMayAccessModel()} is
     * enforced before the activities are loaded.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function activities(Request $request, int $id)
    {
        $soa = $this->soa->findOrFail($id);
        CommonHelper::assertUserMayAccessModel($request, $soa);

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
     * Display paginated concerns linked to the given SOA (on-demand).
     */
    public function concerns(Request $request, int $id)
    {
        $soa = $this->soa->findOrFail($id);
        CommonHelper::assertUserMayAccessModel($request, $soa);

        $perPage = (int) $request->get('per_page', config('vc.default_pages'));
        $concerns = $soa->concerns()
            ->with(['user', 'soas'])
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json(['concerns' => new CommonResource(ConcernResource::collection($concerns))]);
    }

    /**
     * Display paginated account payments (remittance advices) linked to the given SOA (on-demand).
     */
    public function soaAccountPayments(Request $request, int $id)
    {
        $soa = $this->soa->findOrFail($id);
        CommonHelper::assertUserMayAccessModel($request, $soa);

        $perPage = (int) $request->get('per_page', config('vc.default_pages'));
        $accountPayments = $soa->accountPayments()
            ->with(['user', 'soas'])
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json(['account_payments' => new CommonResource(AccountPaymentResource::collection($accountPayments))]);
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
        if (!$user || !$user->hasAnyRole(['account_branch_admin', 'group_account_admin'])) {
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
     * Return the specified SOA and lookup lists for the edit form (AJAX only).
     *
     * Resolves the SOA and includes account-type/bill-type/status/billing-ref
     * option lists. Non-AJAX requests fall through and receive no content.
     *
     * Access control (RBAC): beyond route-level middleware,
     * {@see CommonHelper::assertUserMayAccessModel()} enforces per-model ownership.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function edit(Request $request, int $id)
    {
        $soa = $this->soa->findOrFail($id);
        CommonHelper::assertUserMayAccessModel($request, $soa);

        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'soa' => $soa,
                'account_types' => AccountType::list(),
                'bill_types' => BillType::list(),
                'status_types' => SoaStatus::list(),
                'billing_ref_from_types' => BillRefFrom::list(),
            ]);
        }
    }

    /**
     * Update the specified SOA, recording an audit activity and firing emails.
     *
     * Runs inside a DB transaction: resolves the SOA, rejects updates to a PAID
     * SOA, and (for non account/group admins only) stores any uploaded files.
     * After applying validated changes it diffs the filtered original vs. changed
     * attributes and, when anything changed, records an "update" activity. It then
     * emails a {@see NewBillingInvoiceUploaded} notice when a new PDF is uploaded
     * on an UNPAID SOA, and a {@see BillingInvoiceStatusChanged} notice when an
     * account/group admin moves the status into an allowed value. Returns an
     * HTTP 200 envelope for AJAX requests; rolls back and returns a server-error
     * envelope on failure. Input is validated by {@see UpdateRequest}.
     *
     * Access control (RBAC): {@see CommonHelper::assertUserMayAccessModel()} is
     * enforced before the transaction begins.
     *
     * @param UpdateRequest $request
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function update(UpdateRequest $request)
    {
        $validated = $request->validated();
        $soa = $this->soa->findOrFail($validated['id']);
        CommonHelper::assertUserMayAccessModel($request, $soa);
        DB::beginTransaction();

        try {
            $soa = DB::transaction(function () use ($validated, $request, $soa) {
                CommonHelper::validateNotPaid($soa, SoaStatus::PAID);

                $soaNumber = $validated['soa_number'] ?? $soa->soa_number;
                $canUploadFiles = !auth()->user()->hasAnyRole(['account_branch_admin', 'group_account_admin']);
                if ($canUploadFiles) {
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
                        && $request->user()->hasAnyRole(['account_branch_admin', 'group_account_admin'])
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
                return CustomResponse::serverError($e, 'SoaController');
            }
        }
    }

    /**
     * Display the Find Member search page, or return paginated JSON results.
     * The DB call is intentionally inside the wantsJson() branch so the initial
     * Inertia page render never triggers an HMS query.
     */
    public function findMember(FindMemberRequest $request)
    {
        if ($request->wantsJson()) {
            $members = (new $this->sqlDatabase(Server::HMS))->getMembersByParams($request->validated());

            return response()->json([
                'data'         => MemberResource::collection($members)->resolve(),
                'current_page' => $members->currentPage(),
                'last_page'    => $members->lastPage(),
                'total'        => $members->total(),
                'per_page'     => $members->perPage(),
            ]);
        }

        return Inertia::render('soas/FindMember');
    }

    /**
     * Return RM attachment files for a given claim number.
     * Mirrors the file-listing logic of fileList() without requiring an SOA context.
     */
    public function memberFiles(MemberFilesRequest $request)
    {
        $claimnum = $request->validated('claimnum');

        // Confine RM document access to claims the caller's account owns (F-02).
        if (!(new $this->sqlDatabase(Server::HMS))->userCanAccessClaim($claimnum)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $userId = (int) auth()->id();

        $paths = Storage::disk(config('vc.disks.rm'))->files($claimnum);

        $files = array_map(function (string $path) use ($userId) {
            return [
                'name' => basename($path),
                'preview_token' => CommonHelper::createFilePreviewToken(
                    config('vc.disks.rm'),
                    $path,
                    $userId
                ),
            ];
        }, $paths);

        return response()->json(['files' => $files]);
    }

    /**
     * Toggle soft-delete state for the specified SOA (delete or restore).
     *
     * Runs inside a DB transaction: resolves the SOA including trashed rows,
     * then restores it if already trashed or soft-deletes it otherwise, and
     * reports which action was taken. Responds with a JSON envelope for AJAX
     * requests, or a server-error envelope on failure.
     *
     * Access control (RBAC): beyond the route-level Spatie role/permission
     * middleware, {@see CommonHelper::assertUserMayAccessModel()} enforces
     * per-model ownership before the destructive action; input is validated by
     * {@see DestroyRequest}.
     *
     * @param DestroyRequest $request
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function destroy(DestroyRequest $request)
    {
        $validated = $request->validated();
        DB::beginTransaction();
        try {
            $soa = Soa::withTrashed()->findOrFail($validated['id']);
            CommonHelper::assertUserMayAccessModel($request, $soa);
            if ($soa->trashed()) {
                $soa->restore();
                $label = 'restored';
            } else {
                $soa->delete();
                $label = 'deleted';
            }
            DB::commit();
            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::ok("SOA {$label} successfully", Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::serverError($e, 'SoaController::destroy');
            }
        }
    }
}
