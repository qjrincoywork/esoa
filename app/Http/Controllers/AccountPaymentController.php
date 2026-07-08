<?php

namespace App\Http\Controllers;

use App\Enums\AccountPaymentMode;
use App\Helpers\CommonHelper;
use App\Helpers\CustomResponse;
use App\Http\Requests\AccountPayment\{
    CreateRequest,
    DeleteRequest,
    ListRequest,
    UpdateRequest
};
use App\Http\Resources\AccountPaymentResource;
use App\Http\Resources\CommonResource;
use App\Mail\AccountPaymentNotification;
use App\Models\AccountPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class AccountPaymentController extends Controller
{
    /**
     * AccountPayment model instance.
     *
     * @var AccountPayment
     */
    protected $accountPayment;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        $this->accountPayment = new AccountPayment();
    }

    /**
     * Display a paginated, filterable listing of account payments.
     *
     * Renders the Inertia "account_payments/Index" page with the account-payment
     * collection plus the mode-of-payment option list used by the filter UI.
     *
     * Access control (RBAC): route-level Spatie role/permission middleware gates
     * the endpoint; filters are validated by {@see ListRequest}.
     *
     * @param ListRequest $request
     * @return \Inertia\Response
     */
    public function index(ListRequest $request)
    {
        $validated = $request->validated();
        $accountPayments = $this->accountPayment->getAccountPayments($validated);

        return Inertia::render('account_payments/Index', [
            'account_payments' => new CommonResource(AccountPaymentResource::collection($accountPayments)),
            'mode_of_payment_options' => AccountPaymentMode::list(),
        ]);
    }

    /**
     * Return the lookup lists required to build the "Create Account Payment" form.
     *
     * Responds only to AJAX/JSON requests with the mode-of-payment option list,
     * so the form can be populated without a full page navigation. Non-AJAX
     * requests fall through and receive no content.
     *
     * Access control (RBAC): route-level Spatie role/permission middleware
     * restricts this endpoint to users authorized to create an account payment;
     * the response contains reference data only.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function create(Request $request)
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'mode_of_payment_options' => AccountPaymentMode::list(),
            ]);
        }
    }

    /**
     * Persist a newly created account payment with its linked SOAs and attachment.
     *
     * Runs inside a DB transaction: creates the account payment, syncs any
     * related SOA ids, stores an uploaded remittance-advice file (image/pdf/excel
     * on the account-payments disk, persisting the resulting path back onto the
     * model), then commits and sends an {@see AccountPaymentNotification} email.
     * Rolls back and returns a server-error envelope on failure.
     *
     * Access control (RBAC): route-level Spatie role/permission middleware
     * restricts this endpoint to users authorized to create an account payment;
     * input is validated by {@see CreateRequest}.
     *
     * @param CreateRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CreateRequest $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validated();
            $accountPayment = AccountPayment::create($validated);
            if (!empty($validated['soa_ids'])) {
                // Attach ids
                $accountPayment->soas()->sync(
                    $validated['soa_ids']
                );
            }

            // Store uploaded files (may throw). This will populate $validated with
            // stored paths which we then persist to the created model.
            CommonHelper::storeUploadedFile(
                $request,
                $validated,
                ['image', 'pdf', 'excel'],
                null,
                $accountPayment,
                config('vc.disks.account_payments')
            );

            // Persist any stored file paths onto the model before committing.
            $accountPayment->update($validated);

            DB::commit();

            CommonHelper::sendNotificationEmail($accountPayment, $request->user(), AccountPaymentNotification::class);

            return response()->json(['message' => 'Account payment created successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();

            return CustomResponse::serverError($e, 'AccountPaymentController::store');
        }
    }

    /**
     * Display a single account payment.
     *
     * Eager-loads the account payment's user and SOA relations, wraps it in an
     * {@see AccountPaymentResource}, and renders the Inertia
     * "account_payments/Show" page. Resolved via route-model binding.
     *
     * Access control (RBAC): route-level Spatie role/permission middleware gates
     * the endpoint.
     *
     * @param AccountPayment $accountPayment
     * @return \Inertia\Response
     */
    public function show(AccountPayment $accountPayment)
    {
        CommonHelper::assertUserMayAccessModel(request(), $accountPayment);
        $accountPayment->load(['user', 'soas']);
        $accountPayment = AccountPaymentResource::make($accountPayment);

        return Inertia::render('account_payments/Show', [
            'account_payment' => $accountPayment,
        ]);
    }

    /**
     * Stream a stored remittance-advice attachment inline from a signed token.
     *
     * The token — issued by {@see CommonHelper::createFilePreviewToken()} in
     * edit() — carries the disk, path and issuing user id, so authorization is
     * enforced by the token itself rather than a role check here.
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
            config('vc.disks.account_payments'),
            $request->user()?->id
        );
    }

    /**
     * Return the specified account payment and lookup lists for the edit form (AJAX only).
     *
     * Resolves the account payment with its SOAs, attaches a signed
     * remittance-advice preview token when one exists and the request is
     * authenticated, then responds with the account payment plus the
     * mode-of-payment option list for AJAX requests. Non-AJAX requests fall
     * through and receive no content.
     *
     * Access control (RBAC): beyond the route-level Spatie role/permission
     * middleware, {@see CommonHelper::assertUserMayAccessModel()} enforces
     * per-model ownership before the account payment is loaded.
     *
     * @param int|string $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function edit($id, Request $request)
    {
        $accountPayment = $this->accountPayment->with('soas')->findOrFail($id);
        CommonHelper::assertUserMayAccessModel($request, $accountPayment);
        if ($accountPayment) {
            $accountPayment->remittance_advice_preview_token = $accountPayment->remittance_advice && $request->user()
                ? CommonHelper::createFilePreviewToken(
                    config('vc.disks.account_payments'),
                    $accountPayment->remittance_advice,
                    (int) $request->user()->id
                )
                : null;
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'account_payment' => $accountPayment,
                'mode_of_payment_options' => AccountPaymentMode::list(),
            ]);
        }
    }

    /**
     * Update the specified account payment, its linked SOAs and attachment.
     *
     * Runs inside a DB transaction: resolves the account payment, syncs any
     * related SOA ids, stores a replacement remittance-advice file (image/pdf/
     * excel on the account-payments disk, persisting the resulting path back onto
     * the model), then commits and sends an {@see AccountPaymentNotification}
     * email. Rolls back and returns a server-error envelope on failure.
     *
     * Access control (RBAC): beyond the route-level Spatie role/permission
     * middleware, {@see CommonHelper::assertUserMayAccessModel()} enforces
     * per-model ownership; input is validated by {@see UpdateRequest}.
     *
     * @param UpdateRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateRequest $request)
    {
        $validated = $request->validated();
        $accountPayment = AccountPayment::findOrFail($validated['id']);
        CommonHelper::assertUserMayAccessModel($request, $accountPayment);
        DB::beginTransaction();
        try {
            if (!empty($validated['soa_ids'])) {
                // Attach ids
                $accountPayment->soas()->sync(
                    $validated['soa_ids']
                );
            }
            // Store uploaded files (may throw). This will populate $validated with
            // stored paths which we then persist to the created model.
            CommonHelper::storeUploadedFile(
                $request,
                $validated,
                ['image', 'pdf', 'excel'],
                null,
                $accountPayment,
                config('vc.disks.account_payments')
            );
            // Persist any stored file paths onto the model before committing.
            $accountPayment->update($validated);

            DB::commit();

            CommonHelper::sendNotificationEmail($accountPayment, $request->user(), AccountPaymentNotification::class);

            return response()->json(['message' => 'Account payment updated successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();

            return CustomResponse::serverError($e, 'AccountPaymentController::update');
        }
    }

    /**
     * Toggle soft-delete state for the specified account payment (delete or restore).
     *
     * Runs inside a DB transaction: resolves the account payment including
     * trashed rows, then restores it if already trashed or soft-deletes it
     * otherwise, and reports which action was taken. Responds with a JSON
     * envelope for AJAX requests, or a server-error envelope on failure.
     *
     * Access control (RBAC): beyond the route-level Spatie role/permission
     * middleware, {@see CommonHelper::assertUserMayAccessModel()} enforces
     * per-model ownership before the destructive action; input is validated by
     * {@see DeleteRequest}.
     *
     * @param DeleteRequest $request
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function destroy(DeleteRequest $request)
    {
        $validated = $request->validated();
        $accountPayment = AccountPayment::withTrashed()->findOrFail($validated['id']);
        CommonHelper::assertUserMayAccessModel($request, $accountPayment);
        DB::beginTransaction();
        try {
            if ($accountPayment->trashed()) {
                $accountPayment->restore();
                $message = 'Restored';
            } else {
                $accountPayment->delete();
                $message = 'Deleted';
            }

            DB::commit();

            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::ok('Account payment ' . $message . ' successfully', Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::serverError($e, 'AccountPaymentController::destroy');
            }
        }
    }
}
