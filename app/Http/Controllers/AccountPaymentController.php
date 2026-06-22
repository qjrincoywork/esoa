<?php

namespace App\Http\Controllers;

use App\Enums\AccountPaymentMode;
use App\Enums\RemittanceAdviceStatus;
use App\Enums\SoaStatus;
use App\Helpers\CommonHelper;
use App\Helpers\CustomResponse;
use App\Http\Requests\AccountPayment\{
    ApplyPaymentRequest,
    CreateRequest,
    DeleteRequest,
    ListRequest,
    UpdateRequest,
    UpdateStatusRequest
};
use App\Http\Resources\AccountPaymentResource;
use App\Http\Resources\CommonResource;
use App\Mail\AccountPaymentNotification;
use App\Models\AccountPayment;
use App\Models\Soa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class AccountPaymentController extends Controller
{
    protected AccountPayment $accountPayment;

    public function __construct()
    {
        $this->accountPayment = new AccountPayment();
    }

    public function index(ListRequest $request)
    {
        $validated      = $request->validated();
        $accountPayments = $this->accountPayment->getAccountPayments($validated);

        return Inertia::render('account_payments/Index', [
            'account_payments'        => new CommonResource(AccountPaymentResource::collection($accountPayments)),
            'mode_of_payment_options' => AccountPaymentMode::list(),
            'status_options'          => RemittanceAdviceStatus::list(),
        ]);
    }

    public function create(Request $request)
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'mode_of_payment_options' => AccountPaymentMode::list(),
            ]);
        }
    }

    /**
     * Client creates a new remittance advice. Status is forced to Submitted by CreateRequest.
     */
    public function store(CreateRequest $request)
    {
        DB::beginTransaction();
        try {
            $validated      = $request->validated();
            $accountPayment = AccountPayment::create($validated);

            if (!empty($validated['soa_ids'])) {
                $accountPayment->soas()->sync($validated['soa_ids']);
            }

            CommonHelper::storeUploadedFile(
                $request,
                $validated,
                ['image', 'pdf', 'excel'],
                null,
                $accountPayment,
                env('ACCOUNT_PAYMENTS_DISK', 'public')
            );

            $accountPayment->update($validated);

            $accountPayment->recordActivity('submitted', [
                'from' => null,
                'to'   => [
                    'status' => RemittanceAdviceStatus::label(RemittanceAdviceStatus::SUBMITTED),
                    'amount' => $accountPayment->amount,
                ],
            ]);

            DB::commit();

            CommonHelper::sendNotificationEmail($accountPayment, $request->user(), AccountPaymentNotification::class);

            return response()->json(['message' => 'Remittance advice submitted successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(
                ['message' => 'Failed to submit remittance advice: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function show(AccountPayment $accountPayment)
    {
        $accountPayment->load(['user', 'soas', 'activities.user']);
        $accountPayment = AccountPaymentResource::make($accountPayment);

        return Inertia::render('account_payments/Show', [
            'account_payment' => $accountPayment,
        ]);
    }

    public function previewFile(Request $request)
    {
        return CommonHelper::previewStoredFileFromToken(
            (string) $request->query('token', ''),
            env('ACCOUNT_PAYMENTS_DISK', 'public'),
            $request->user()?->id
        );
    }

    public function edit(int $id, Request $request)
    {
        $accountPayment = $this->accountPayment->with('soas')->findOrFail($id);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'account_payment'         => $accountPayment,
                'mode_of_payment_options' => AccountPaymentMode::list(),
                'status_options'          => RemittanceAdviceStatus::list(),
                'allowed_next_statuses'   => RemittanceAdviceStatus::allowedNext((int) $accountPayment->status),
            ]);
        }
    }

    /**
     * Client updates their own remittance advice (only allowed while status = Submitted).
     */
    public function update(UpdateRequest $request)
    {
        $validated = $request->validated();
        DB::beginTransaction();
        try {
            $accountPayment = AccountPayment::findOrFail($validated['id']);

            if (!empty($validated['soa_ids'])) {
                $accountPayment->soas()->sync($validated['soa_ids']);
            }

            CommonHelper::storeUploadedFile(
                $request,
                $validated,
                ['image', 'pdf', 'excel'],
                null,
                $accountPayment,
                env('ACCOUNT_PAYMENTS_DISK', 'public')
            );

            $accountPayment->update($validated);

            DB::commit();

            CommonHelper::sendNotificationEmail($accountPayment, $request->user(), AccountPaymentNotification::class);

            return response()->json(['message' => 'Remittance advice updated successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(
                ['message' => 'Failed to update remittance advice: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Billing department updates the remittance advice status only.
     * Validates allowed transitions via UpdateStatusRequest.
     */
    public function updateStatus(UpdateStatusRequest $request)
    {
        $validated = $request->validated();
        DB::beginTransaction();
        try {
            $accountPayment = AccountPayment::findOrFail($validated['id']);
            $previousStatus = (int) $accountPayment->status;
            $nextStatus     = (int) $validated['status'];

            $accountPayment->update([
                'status'  => $nextStatus,
                'remarks' => $validated['remarks'] ?? $accountPayment->remarks,
            ]);

            $accountPayment->recordActivity('status_updated', [
                'from' => ['status' => RemittanceAdviceStatus::label($previousStatus)],
                'to'   => ['status' => RemittanceAdviceStatus::label($nextStatus)],
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Remittance advice status updated to "' . RemittanceAdviceStatus::label($nextStatus) . '".',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(
                ['message' => 'Failed to update status: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Billing department applies the remittance advice payment to one or more SOAs.
     *
     * Over-payment handling:
     *   - If requested_amount > SOA balance, only the SOA balance is applied.
     *   - The remainder is retained as credit on the RA (credit = RA amount - total applied).
     *   - Credit can be applied to future SOAs via subsequent calls to this endpoint.
     *
     * SOA status after application:
     *   - PAID           when applied_amount covers the full SOA balance.
     *   - PARTIALLY_PAID when applied_amount covers only part of the SOA balance.
     *
     * RA status after application:
     *   - FULLY_APPLIED   when credit_balance reaches zero.
     *   - PARTIALLY_APPLIED when credit_balance > 0.
     */
    public function applyPayment(ApplyPaymentRequest $request)
    {
        $validated = $request->validated();
        DB::beginTransaction();
        try {
            $accountPayment   = AccountPayment::with('soas')->findOrFail($validated['id']);
            $raAmount         = (float) $accountPayment->amount;
            $previousRaStatus = (int) $accountPayment->status;

            $syncData        = [];
            $activityDetails = [];

            foreach ($validated['applications'] as $application) {
                $soa            = Soa::findOrFail($application['soa_id']);
                $soaBalance     = (float) $soa->amount;
                $requested      = (float) $application['applied_amount'];
                // Cap at SOA balance; excess becomes credit on the RA.
                $actualApplied  = min($requested, $soaBalance);
                $excess         = $requested - $actualApplied;

                $syncData[$soa->id] = ['applied_amount' => $actualApplied];

                $newSoaStatus      = $actualApplied >= $soaBalance
                    ? SoaStatus::PAID
                    : SoaStatus::PARTIALLY_PAID;

                $previousSoaStatus = (int) $soa->status;
                $soa->update(['status' => $newSoaStatus]);

                $soaActivityPayload = [
                    'status'         => SoaStatus::label($newSoaStatus),
                    'applied_amount' => $actualApplied,
                    'ra_id'          => $accountPayment->id,
                ];
                if ($excess > 0) {
                    $soaActivityPayload['excess_credited_to_ra'] = $excess;
                }

                $soa->recordActivity('payment_applied', [
                    'from' => ['status' => SoaStatus::label($previousSoaStatus)],
                    'to'   => $soaActivityPayload,
                ]);

                $activityDetails[] = [
                    'soa_number'     => $soa->soa_number,
                    'requested'      => $requested,
                    'applied_amount' => $actualApplied,
                    'excess'         => $excess,
                    'soa_status'     => SoaStatus::label($newSoaStatus),
                ];
            }

            // Persist pivot updates (preserves any previously linked SOAs not in this batch).
            $accountPayment->soas()->syncWithoutDetaching($syncData);

            // Re-query total applied AFTER sync so it reflects the updated pivot values.
            $totalApplied  = $accountPayment->totalApplied();
            $creditBalance = max(0, $raAmount - $totalApplied);

            $newRaStatus = $creditBalance <= 0
                ? RemittanceAdviceStatus::FULLY_APPLIED
                : RemittanceAdviceStatus::PARTIALLY_APPLIED;

            $accountPayment->update(['status' => $newRaStatus]);

            $accountPayment->recordActivity('payment_applied', [
                'from' => ['status' => RemittanceAdviceStatus::label($previousRaStatus)],
                'to'   => [
                    'status'         => RemittanceAdviceStatus::label($newRaStatus),
                    'ra_amount'      => $raAmount,
                    'total_applied'  => $totalApplied,
                    'credit_balance' => $creditBalance,
                    'applications'   => $activityDetails,
                ],
            ]);

            DB::commit();

            $message = 'Payment applied successfully. RA status: ' . RemittanceAdviceStatus::label($newRaStatus) . '.';
            if ($creditBalance > 0) {
                $message .= sprintf(' Credit balance of ₱%.2f retained for future applications.', $creditBalance);
            }

            return response()->json([
                'message'        => $message,
                'credit_balance' => $creditBalance,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(
                ['message' => 'Failed to apply payment: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function destroy(DeleteRequest $request)
    {
        $validated = $request->validated();
        DB::beginTransaction();
        try {
            $accountPayment = AccountPayment::withTrashed()->findOrFail($validated['id']);

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
                return CustomResponse::error($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
    }
}
