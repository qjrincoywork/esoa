<?php

namespace App\Http\Controllers;

use App\Enums\Status;
use App\Helpers\CustomResponse;
use App\Http\Requests\Navigation\{CreateRequest, DeleteRequest, ListRequest, UpdateRequest};
use App\Http\Resources\NavigationResource;
use App\Models\Navigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class NavigationController extends Controller
{
    /**
     * Navigation model instance.
     *
     * @var Navigation
     */
    protected $navigation;

    /**
     * NavigationController constructor.
     *
     * @param Navigation $navigation
     *
     * @return void
     */
    public function __construct(Navigation $navigation)
    {
        $this->navigation = $navigation;
    }

    /**
     * Render the Inertia "navigations/Index" page with the filtered navigation list.
     *
     * Filters are validated by {@see ListRequest}; access is gated by route-level
     * Spatie role/permission middleware.
     *
     * @param ListRequest $request
     * @return \Inertia\Response
     */
    public function index(ListRequest $request)
    {
        $navs = $this->navigation->getNavigations($request->validated())->toArray();

        return Inertia::render('navigations/Index', [
            'navigation_list' => $navs
        ]);
    }

    /**
     * Return the status option list for the "Create Navigation" form (AJAX only).
     *
     * Non-AJAX requests fall through and receive no content.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function create(Request $request)
    {
        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'statuses' => Status::list(),
            ]);
        }
    }

    /**
     * Persist a new navigation inside a DB transaction.
     *
     * Delegates to Navigation::saveNavigation(), commits, and returns an HTTP 201
     * envelope for AJAX requests. Rolls back and returns a server-error envelope
     * on failure. Input is validated by {@see CreateRequest}.
     *
     * @param CreateRequest $request
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function store(CreateRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $this->navigation->saveNavigation($validated);

            // Commit transaction
            DB::commit();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::created('Navigation Created successfully', Response::HTTP_CREATED);
            }
        } catch (\Exception $e) {
            // Catch and handle any unexpected errors
            DB::rollBack();

            return CustomResponse::serverError($e, 'NavigationController::store');
        }
    }

    /**
     * Unused resource stub; navigations are not shown individually.
     *
     * @param Navigation $navigation
     * @return void
     */
    public function show(Navigation $navigation)
    {
        //
    }

    /**
     * Return the navigation and status option list for the edit form (AJAX only).
     *
     * Resolves the navigation via findOrFail(); non-AJAX requests fall through
     * and receive no content.
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function edit(int $id, Request $request)
    {
        $navigation = $this->navigation->findOrFail($id);

        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'navigation' => $navigation,
                'statuses' => Status::list(),
            ]);
        }
    }

    /**
     * Update a navigation inside a DB transaction.
     *
     * Delegates to Navigation::saveNavigation(), commits, and returns an HTTP 200
     * envelope for AJAX requests. Rolls back and returns a server-error envelope
     * on failure. Input is validated by {@see UpdateRequest}.
     *
     * @param UpdateRequest $request
     * @param Navigation $navigation
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function update(UpdateRequest $request, Navigation $navigation)
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $this->navigation->saveNavigation($validated);

            // Commit transaction
            DB::commit();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::ok('Navigation Updated successfully', Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            // Catch and handle any unexpected errors
            DB::rollBack();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                // Catch and handle any unexpected errors
                return CustomResponse::serverError($e, 'NavigationController::update');
            }
        }
    }

    /**
     * Toggle soft-delete state for the specified navigation (delete or restore).
     *
     * Runs inside a DB transaction: resolves the navigation including trashed
     * rows, restores it if already trashed or soft-deletes it otherwise, then
     * commits and returns an HTTP 200 envelope for AJAX requests. Rolls back and
     * returns a server-error envelope on failure. Input is validated by
     * {@see DeleteRequest}.
     *
     * @param DeleteRequest $request
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function destroy(DeleteRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $navigation = $this->navigation
                ->withTrashed()
                ->find($validated['id']);

            if ($navigation->trashed()) {
                $navigation->restore();
                $message = 'Restored';
            } else {
                $navigation->delete();
                $message = 'Deleted';
            }

            // Commit transaction
            DB::commit();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::ok('Navigation ' . $message . ' successfully', Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            // Catch and handle any unexpected errors
            DB::rollBack();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::serverError($e, 'NavigationController::destroy');
            }
        }
    }
}
