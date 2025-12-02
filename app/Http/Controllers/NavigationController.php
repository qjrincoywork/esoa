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
     * Display a listing of the resource.
     */
    public function index(ListRequest $request)
    {
        $navs = $this->navigation->getNavigations($request->validated())->toArray();

        return Inertia::render('navigations/Index', [
            'navigation_list' => $navs
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
                'statuses' => Status::list(),
            ]);
        }
    }

    /**
     * Store a newly created resource in storage.
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

            return CustomResponse::error($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Navigation $navigation)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
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
     * Update the specified resource in storage.
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
                return CustomResponse::error($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }
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
                return CustomResponse::error($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
    }
}
