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
        DB::connection('mysql')->beginTransaction();
        try {
            $validated = $request->validated();
            CommonHelper::storeUploadedFile(
                $request,
                $validated,
                'remittance_advice',
                null,
                null,
                env('ACCOUNT_PAYMENTS_DISK', 'public')
            );
            $accountPayment = AccountPayment::create($validated);
            DB::connection('mysql')->commit();

            CommonHelper::sendNotificationEmail($accountPayment, $request->user(), AccountPaymentNotification::class);

            return response()->json(['message' => 'Account payment created successfully.']);
        } catch (\Exception $e) {
            DB::connection('mysql')->rollBack();

            return response()->json(['message' => 'Failed to create account payment: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(AccountPayment $accountPayment)
    {
        $accountPayment->load(['user']);
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
        $accountPayment = $this->accountPayment->findOrFail($id);
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
        DB::connection('mysql')->beginTransaction();
        try {
            $accountPayment = AccountPayment::findOrFail($validated['id']);
            CommonHelper::storeUploadedFile(
                $request,
                $validated,
                'remittance_advice',
                null,
                $accountPayment,
                env('ACCOUNT_PAYMENTS_DISK', 'public')
            );
            $accountPayment->update($validated);

            DB::connection('mysql')->commit();

            CommonHelper::sendNotificationEmail($accountPayment, $request->user(), AccountPaymentNotification::class);

            return response()->json(['message' => 'Account payment updated successfully.']);
        } catch (\Exception $e) {
            DB::connection('mysql')->rollBack();

            return response()->json(['message' => 'Failed to update account payment: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeleteRequest $request)
    {
        $validated = $request->validated();
        DB::connection('mysql')->beginTransaction();
        try {
            $accountPayment = AccountPayment::withTrashed()->findOrFail($validated['id']);

            if ($accountPayment->trashed()) {
                $accountPayment->restore();
                $message = 'Restored';
            } else {
                $accountPayment->delete();
                $message = 'Deleted';
            }

            DB::connection('mysql')->commit();

            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::ok('Account payment ' . $message . ' successfully', Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            DB::connection('mysql')->rollBack();

            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::error($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
    }
}
