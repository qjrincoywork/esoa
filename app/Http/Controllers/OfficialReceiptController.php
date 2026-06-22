<?php

namespace App\Http\Controllers;

use App\Helpers\CommonHelper;
use App\Helpers\CustomResponse;
use App\Http\Requests\OfficialReceipt\{
    CreateRequest,
    DeleteRequest,
    ListRequest,
    UpdateRequest
};
use App\Http\Resources\CommonResource;
use App\Http\Resources\OfficialReceiptResource;
use App\Models\OfficialReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class OfficialReceiptController extends Controller
{
    protected OfficialReceipt $officialReceipt;

    public function __construct()
    {
        $this->officialReceipt = new OfficialReceipt();
    }

    public function index(ListRequest $request)
    {
        $validated       = $request->validated();
        $officialReceipts = $this->officialReceipt->getOfficialReceipts($validated);

        return Inertia::render('official_receipts/Index', [
            'official_receipts' => new CommonResource(OfficialReceiptResource::collection($officialReceipts)),
        ]);
    }

    /** Returns form options for a create modal (JSON). */
    public function create(Request $request)
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([]);
        }
    }

    /**
     * Billing department issues a new Official Receipt and links it to one or more SOAs.
     */
    public function store(CreateRequest $request)
    {
        DB::beginTransaction();
        try {
            $validated      = $request->validated();
            $officialReceipt = OfficialReceipt::create($validated);

            $officialReceipt->soas()->sync($validated['soa_ids']);

            CommonHelper::storeUploadedFile(
                $request,
                $validated,
                ['file'],
                null,
                $officialReceipt,
                env('OFFICIAL_RECEIPTS_DISK', 'public')
            );

            $officialReceipt->update($validated);

            DB::commit();

            return response()->json(['message' => 'Official receipt issued successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(
                ['message' => 'Failed to issue official receipt: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function show(OfficialReceipt $officialReceipt)
    {
        $officialReceipt->load(['user', 'soas', 'accountPayment']);
        $officialReceipt = OfficialReceiptResource::make($officialReceipt);

        return Inertia::render('official_receipts/Show', [
            'official_receipt' => $officialReceipt,
        ]);
    }

    public function previewFile(Request $request)
    {
        return CommonHelper::previewStoredFileFromToken(
            (string) $request->query('token', ''),
            env('OFFICIAL_RECEIPTS_DISK', 'public'),
            $request->user()?->id
        );
    }

    public function edit(int $id, Request $request)
    {
        $officialReceipt = $this->officialReceipt->with('soas')->findOrFail($id);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'official_receipt' => $officialReceipt,
            ]);
        }
    }

    /**
     * Billing department updates an existing Official Receipt.
     */
    public function update(UpdateRequest $request)
    {
        $validated = $request->validated();
        DB::beginTransaction();
        try {
            $officialReceipt = OfficialReceipt::findOrFail($validated['id']);

            $officialReceipt->soas()->sync($validated['soa_ids']);

            CommonHelper::storeUploadedFile(
                $request,
                $validated,
                ['file'],
                null,
                $officialReceipt,
                env('OFFICIAL_RECEIPTS_DISK', 'public')
            );

            $officialReceipt->update($validated);

            DB::commit();

            return response()->json(['message' => 'Official receipt updated successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(
                ['message' => 'Failed to update official receipt: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function destroy(DeleteRequest $request)
    {
        $validated = $request->validated();
        DB::beginTransaction();
        try {
            $officialReceipt = OfficialReceipt::withTrashed()->findOrFail($validated['id']);

            if ($officialReceipt->trashed()) {
                $officialReceipt->restore();
                $message = 'Restored';
            } else {
                $officialReceipt->delete();
                $message = 'Deleted';
            }

            DB::commit();

            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::ok('Official receipt ' . $message . ' successfully.', Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::error($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
    }
}
