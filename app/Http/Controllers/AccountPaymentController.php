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
     * Display a listing of the resource.
     */
    protected $accountPayment;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->accountPayment = new AccountPayment();
    }

    /**
     * Display a listing of the resource.
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
     * Show the form for creating a new resource.
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
     * Store a newly created resource in storage.
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
                env('ACCOUNT_PAYMENTS_DISK', 'public')
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
     * Display the specified resource.
     */
    public function show(AccountPayment $accountPayment)
    {
        $accountPayment->load(['user', 'soas']);
        $accountPayment = AccountPaymentResource::make($accountPayment);

        return Inertia::render('account_payments/Show', [
            'account_payment' => $accountPayment,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function previewFile(Request $request)
    {
        return CommonHelper::previewStoredFileFromToken(
            (string) $request->query('token', ''),
            env('ACCOUNT_PAYMENTS_DISK', 'public'),
            $request->user()?->id
        );
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id, Request $request)
    {
        $accountPayment = $this->accountPayment->with('soas')->findOrFail($id);
        if ($accountPayment) {
            $accountPayment->remittance_advice_preview_token = $accountPayment->remittance_advice && $request->user()
                ? CommonHelper::createFilePreviewToken(
                    env('ACCOUNT_PAYMENTS_DISK', 'public'),
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
     * Update the specified resource in storage.
     */
    public function update(UpdateRequest $request)
    {
        $validated = $request->validated();
        DB::beginTransaction();
        try {
            $accountPayment = AccountPayment::findOrFail($validated['id']);

            $authUser = $request->user();
            if (!$authUser->hasAnyRole(['superadmin', 'admin']) && $accountPayment->user_id !== $authUser->id) {
                return CustomResponse::error('Forbidden', Response::HTTP_FORBIDDEN);
            }
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
                env('ACCOUNT_PAYMENTS_DISK', 'public')
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
     * Remove the specified resource from storage.
     */
    public function destroy(DeleteRequest $request)
    {
        $validated = $request->validated();
        DB::beginTransaction();
        try {
            $accountPayment = AccountPayment::withTrashed()->findOrFail($validated['id']);

            $authUser = $request->user();
            if (!$authUser->hasAnyRole(['superadmin', 'admin']) && $accountPayment->user_id !== $authUser->id) {
                return CustomResponse::error('Forbidden', Response::HTTP_FORBIDDEN);
            }

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
