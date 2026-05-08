<?php

namespace App\Http\Controllers;

use App\Enums\{ConcernType, TicketStatus};
use App\Helpers\CommonHelper;
use App\Helpers\CustomResponse;
use App\Mail\ConcernNotification;
use App\Models\Concern;
use App\Http\Requests\Concern\{CreateRequest, DeleteRequest, ListRequest, UpdateRequest};
use App\Http\Resources\CommonResource;
use App\Http\Resources\ConcernResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ConcernController extends Controller
{
    /**
     * Concern model instance.
     *
     * @var Concern
     */
    protected $concern;

    /**
     * Constructor
     *
     * @param Concern $concern
     * @return void
     */
    public function __construct()
    {
        $this->concern = new Concern();
    }

    /**
     * Display a listing of the resource.
     */
    public function index(ListRequest $request)
    {
        $validated = $request->validated();
        $concerns = $this->concern->getConcerns($validated);

        return Inertia::render('concerns/Index', [
            'concerns' => new CommonResource(ConcernResource::collection($concerns)),
            'concern_types' => ConcernType::list(),
            'ticket_statuses' => TicketStatus::list(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'concern_types' => ConcernType::list(),
                'ticket_statuses' => TicketStatus::list(),
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
            CommonHelper::storeUploadedFile($request, $validated, 'attachment');
            $concern = Concern::create($validated);

            DB::connection('mysql')->commit();

            CommonHelper::sendNotificationEmail($concern, $request->user(), ConcernNotification::class);

            return response()->json(['message' => 'Concern created successfully.']);
        } catch (\Exception $e) {
            DB::connection('mysql')->rollBack();

            return response()->json(['message' => 'Failed to create concern: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Concern $concern)
    {
        $concern->load(['user', 'soa']);

        return Inertia::render('concerns/Show', [
            'concern' => $concern,
        ]);
    }

    /**
     * Preview a stored concern attachment.
     */
    public function previewFile(Request $request)
    {
        return CommonHelper::previewStoredFileFromToken(
            (string) $request->query('token', ''),
            env('CONCERNS_DISK', 'public'),
            $request->user()?->id
        );
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id, Request $request)
    {
        $concern = $this->concern->findOrFail($id);
        if ($concern) {
            $concern->attachment_preview_token = $concern->attachment && $request->user()
                ? CommonHelper::createFilePreviewToken(
                    env('CONCERNS_DISK', 'public'),
                    $concern->attachment,
                    (int) $request->user()->id
                )
                : null;
        }

        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'concern' => $concern,
                'concern_types' => ConcernType::list(),
                'ticket_statuses' => TicketStatus::list(),
            ]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRequest $request)
    {
        DB::connection('mysql')->beginTransaction();
        try {
            $concern = Concern::findOrFail($request->id);
            $validated = $request->validated();
            CommonHelper::storeUploadedFile($request, $validated, 'attachment');
            $concern->update($validated);

            DB::connection('mysql')->commit();

            CommonHelper::sendNotificationEmail($concern, $request->user(), ConcernNotification::class);

            return response()->json(['message' => 'Concern updated successfully.']);
        } catch (\Exception $e) {
            DB::connection('mysql')->rollBack();

            return response()->json(['message' => 'Failed to update concern: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
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
            $concern = Concern::withTrashed()->findOrFail($validated['id']);

            if ($concern->trashed()) {
                $concern->restore();
                $message = 'Restored';
            } else {
                $concern->delete();
                $message = 'Deleted';
            }

            // Commit transaction
            DB::commit();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::ok('Concern ' . $message . ' successfully', Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            // Catch and handle any unexpected errors
            DB::rollBack();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::error($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
    }
}
