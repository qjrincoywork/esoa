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
     * Display a paginated, filterable listing of concerns.
     *
     * Renders the Inertia "concerns/Index" page with the concern collection plus
     * the concern-type and ticket-status option lists used by the filter UI.
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
        $concerns = $this->concern->getConcerns($validated);

        return Inertia::render('concerns/Index', [
            'concerns' => new CommonResource(ConcernResource::collection($concerns)),
            'concern_types' => ConcernType::list(),
            'ticket_statuses' => TicketStatus::list(),
        ]);
    }

    /**
     * Return the lookup lists required to build the "Create Concern" form.
     *
     * Responds only to AJAX/JSON requests with the concern-type and
     * ticket-status option lists, so the form can be populated without a full
     * page navigation. Non-AJAX requests fall through and receive no content.
     *
     * Access control (RBAC): route-level Spatie role/permission middleware
     * restricts this endpoint to users authorized to create a concern; the
     * response contains reference data only.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
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
     * Persist a newly created concern together with its linked SOAs and attachment.
     *
     * Runs inside a DB transaction: creates the concern, syncs any related SOA
     * ids, stores an uploaded attachment (persisting the resulting path back onto
     * the model), then commits and sends a {@see ConcernNotification} email.
     * Rolls back and returns a server-error envelope on failure.
     *
     * Access control (RBAC): route-level Spatie role/permission middleware
     * restricts this endpoint to users authorized to create a concern; input is
     * validated by {@see CreateRequest}.
     *
     * @param CreateRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CreateRequest $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validated();
            $concern = Concern::create($validated);
            if (!empty($validated['soa_ids'])) {
                // Attach ids
                $concern->soas()->sync(
                    $validated['soa_ids']
                );
            }
            // Store uploaded files (may throw). This will populate $validated with
            // stored paths which we then persist to the created model.
            CommonHelper::storeUploadedFile(
                $request,
                $validated,
                'attachment',
                null,
                $concern,
            );

            // Persist any stored file paths onto the model before committing.
            $concern->update($validated);

            DB::commit();

            CommonHelper::sendNotificationEmail($concern, $request->user(), ConcernNotification::class);

            return response()->json(['message' => 'Concern created successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();

            return CustomResponse::serverError($e, 'ConcernController::store');
        }
    }

    /**
     * Display a single concern.
     *
     * Eager-loads the concern's user and SOA relations and renders the Inertia
     * "concerns/Show" page. The concern is resolved via route-model binding.
     *
     * Access control (RBAC): route-level Spatie role/permission middleware gates
     * the endpoint.
     *
     * @param Concern $concern
     * @return \Inertia\Response
     */
    public function show(Concern $concern)
    {
        CommonHelper::assertUserMayAccessModel(request(), $concern);
        $concern->load(['user', 'soa']);

        return Inertia::render('concerns/Show', [
            'concern' => $concern,
        ]);
    }

    /**
     * Stream a stored concern attachment inline from a signed preview token.
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
            config('vc.disks.concerns'),
            $request->user()?->id
        );
    }

    /**
     * Return the specified concern and lookup lists for the edit form (AJAX only).
     *
     * Resolves the concern, attaches a signed attachment preview token when the
     * concern has an attachment and the request is authenticated, then responds
     * with the concern plus concern-type/ticket-status option lists for AJAX
     * requests. Non-AJAX requests fall through and receive no content.
     *
     * Access control (RBAC): beyond the route-level Spatie role/permission
     * middleware, {@see CommonHelper::assertUserMayAccessModel()} enforces
     * per-model ownership before the concern is loaded.
     *
     * @param int|string $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function edit($id, Request $request)
    {
        $concern = $this->concern->findOrFail($id);
        CommonHelper::assertUserMayAccessModel($request, $concern);
        if ($concern) {
            $concern->attachment_preview_token = $concern->attachment && $request->user()
                ? CommonHelper::createFilePreviewToken(
                    config('vc.disks.concerns'),
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
     * Update the specified concern, its linked SOAs and attachment.
     *
     * Runs inside a DB transaction: resolves the concern, syncs any related SOA
     * ids, stores a replacement attachment (persisting the resulting path back
     * onto the model), then commits and sends a {@see ConcernNotification} email.
     * Rolls back and returns a server-error envelope on failure.
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
        $concern = Concern::findOrFail($request->id);
        CommonHelper::assertUserMayAccessModel($request, $concern);
        DB::beginTransaction();
        try {
            $validated = $request->validated();
            if (!empty($validated['soa_ids'])) {
                // Attach ids
                $concern->soas()->sync(
                    $validated['soa_ids']
                );
            }
            // Store uploaded files (may throw). This will populate $validated with
            // stored paths which we then persist to the created model.
            CommonHelper::storeUploadedFile(
                $request,
                $validated,
                'attachment',
                null,
                $concern,
            );
            // Persist any stored file paths onto the model before committing.
            $concern->update($validated);

            DB::commit();

            CommonHelper::sendNotificationEmail($concern, $request->user(), ConcernNotification::class);

            return response()->json(['message' => 'Concern updated successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();

            return CustomResponse::serverError($e, 'ConcernController::update');
        }
    }

    /**
     * Toggle soft-delete state for the specified concern (delete or restore).
     *
     * Runs inside a DB transaction: resolves the concern including trashed rows,
     * then restores it if already trashed or soft-deletes it otherwise, and
     * reports which action was taken. Responds with a JSON envelope for AJAX
     * requests, or a server-error envelope on failure.
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
        $concern = Concern::withTrashed()->findOrFail($validated['id']);
        CommonHelper::assertUserMayAccessModel($request, $concern);

        DB::beginTransaction();
        try {
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
                return CustomResponse::serverError($e, 'ConcernController::destroy');
            }
        }
    }
}
