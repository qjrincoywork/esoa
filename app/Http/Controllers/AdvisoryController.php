<?php

namespace App\Http\Controllers;

use App\Enums\AdvisoryState;
use App\Helpers\CommonHelper;
use App\Helpers\CustomResponse;
use App\Models\Advisory;
use App\Http\Requests\Advisory\{CreateRequest, DeleteRequest, ListRequest, ToggleActiveRequest, UpdateRequest};
use App\Http\Resources\AdvisoryResource;
use App\Http\Resources\CommonResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Advisories shown as a pop-up right after signing in ({@see \App\Support\LoginAdvisories}).
 *
 * Everything except {@see image()} is administration, gated by the permission named
 * after each route. The image is read by every signed-in user, so it carries its own
 * rule instead: it is only served while the advisory is live, unless the reader is
 * someone who manages advisories and needs to preview one that is not.
 */
class AdvisoryController extends Controller
{
    /**
     * Advisory model instance.
     *
     * @var Advisory
     */
    protected $advisory;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        $this->advisory = new Advisory();
    }

    /**
     * Display a paginated, filterable listing of advisories.
     *
     * @param ListRequest $request
     * @return \Inertia\Response
     */
    public function index(ListRequest $request)
    {
        $validated = $request->validated();
        $advisories = $this->advisory->getAdvisories($validated);

        return Inertia::render('advisories/Index', [
            'advisories' => new CommonResource(AdvisoryResource::collection($advisories)),
            'advisory_states' => AdvisoryState::list(),
        ]);
    }

    /**
     * Persist a new advisory and its image.
     *
     * The image is stored under the advisory's own id, so the row is created first and
     * the stored path written back before committing.
     *
     * @param CreateRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CreateRequest $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validated();
            unset($validated['image']);

            $advisory = Advisory::create([
                ...$validated,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $this->storeImage($request, $advisory, $validated);
            $advisory->update($validated);

            DB::commit();

            return CustomResponse::ok('Advisory created successfully.', Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();

            return CustomResponse::serverError($e, 'AdvisoryController::store');
        }
    }

    /**
     * Return the specified advisory for the edit form (AJAX only).
     *
     * @param int|string $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function edit($id, Request $request)
    {
        $advisory = $this->advisory->with(['creator', 'updater'])->findOrFail($id);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'advisory' => AdvisoryResource::make($advisory),
            ]);
        }
    }

    /**
     * Update the specified advisory; an uploaded image replaces the current one, an
     * empty file input keeps it.
     *
     * @param UpdateRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateRequest $request)
    {
        $validated = $request->validated();
        $advisory = Advisory::findOrFail($validated['id']);

        DB::beginTransaction();
        try {
            unset($validated['id'], $validated['image']);
            $validated['updated_by'] = $request->user()->id;

            $this->storeImage($request, $advisory, $validated);
            $advisory->update($validated);

            DB::commit();

            return CustomResponse::ok('Advisory updated successfully.', Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();

            return CustomResponse::serverError($e, 'AdvisoryController::update');
        }
    }

    /**
     * Switch an advisory on or off without touching its window.
     *
     * @param ToggleActiveRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleActive(ToggleActiveRequest $request)
    {
        $validated = $request->validated();
        $advisory = Advisory::findOrFail($validated['id']);

        DB::beginTransaction();
        try {
            $advisory->update([
                'is_active' => $validated['is_active'],
                'updated_by' => $request->user()->id,
            ]);

            DB::commit();

            $label = $validated['is_active'] ? 'enabled' : 'disabled';

            return CustomResponse::ok("Advisory {$label} successfully.", Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();

            return CustomResponse::serverError($e, 'AdvisoryController::toggleActive');
        }
    }

    /**
     * Toggle soft-delete state for the specified advisory (delete or restore).
     *
     * @param DeleteRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(DeleteRequest $request)
    {
        $validated = $request->validated();
        $advisory = Advisory::withTrashed()->findOrFail($validated['id']);

        DB::beginTransaction();
        try {
            if ($advisory->trashed()) {
                $advisory->restore();
                $message = 'Restored';
            } else {
                $advisory->delete();
                $message = 'Deleted';
            }

            DB::commit();

            return CustomResponse::ok('Advisory ' . $message . ' successfully.', Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();

            return CustomResponse::serverError($e, 'AdvisoryController::destroy');
        }
    }

    /**
     * Stream an advisory's image inline.
     *
     * Any signed-in user may read the image of a live advisory — that is what the
     * sign-in pop-up shows them. Anything else (scheduled, expired, disabled, deleted)
     * is only served to people who manage advisories, and is a 404 to everyone else so
     * an upcoming notice cannot be read ahead of its window by guessing ids.
     *
     * @param int|string $id
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function image($id, Request $request)
    {
        $user = $request->user();
        $canManage = $user->hasRole('superadmin') || $user->checkPermissionTo('advisories.index');

        $advisory = Advisory::query()
            ->when($canManage, fn ($query) => $query->withTrashed())
            ->findOrFail($id);

        if (!$advisory->image || (!$canManage && !$advisory->isLive())) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $response = CommonHelper::previewStoredFile(config('vc.disks.advisories'), $advisory->image);
        // The URL is versioned by the advisory's last update, so the browser may keep it.
        $response->headers->set('Cache-Control', 'private, max-age=3600');

        return $response;
    }

    /**
     * Store an uploaded image under the advisory's id and put its path into the payload.
     *
     * @param Request $request
     * @param Advisory $advisory
     * @param array $validated
     * @return void
     */
    private function storeImage(Request $request, Advisory $advisory, array &$validated): void
    {
        CommonHelper::storeUploadedFile(
            $request,
            $validated,
            'image',
            (string) $advisory->id,
            $advisory,
            config('vc.disks.advisories'),
        );
    }
}
