<?php

namespace App\Http\Controllers;

use App\Enums\Status;
use App\Helpers\CustomResponse;
use App\Http\Requests\NavigationModule\{CreateRequest, DeleteRequest, ListRequest, UpdateRequest};
use App\Models\{Navigation, NavigationModule};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\Response;

class NavigationModuleController extends Controller
{
    protected NavigationModule $navigationModule;

    /**
     * Inject the NavigationModule model instance.
     */
    public function __construct(NavigationModule $navigationModule)
    {
        $this->navigationModule = $navigationModule;
    }

    /**
     * Render the Inertia "navigation_modules/Index" page with the filtered module list.
     *
     * Filters are validated by {@see ListRequest}.
     *
     * @param ListRequest $request
     * @return \Inertia\Response
     */
    public function index(ListRequest $request)
    {
        $modules = $this->navigationModule->getNavigationModules($request->validated())->toArray();

        return Inertia::render('navigation_modules/Index', [
            'navigation_modules' => $modules,
        ]);
    }

    /**
     * Return form data for creating a new module.
     */
    public function create(Request $request)
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($this->formPayload());
        }
    }

    /**
     * Persist a new navigation module inside a DB transaction.
     *
     * Delegates to NavigationModule::saveNavigationModule(), commits, and returns
     * an HTTP 201 envelope for AJAX requests. Rolls back and returns a
     * server-error envelope on failure. Input is validated by {@see CreateRequest}.
     *
     * @param CreateRequest $request
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function store(CreateRequest $request)
    {
        DB::beginTransaction();

        try {
            $this->navigationModule->saveNavigationModule($request->validated());

            DB::commit();

            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::created('Navigation module created successfully', Response::HTTP_CREATED);
            }
        } catch (\Exception $e) {
            DB::rollBack();

            return CustomResponse::serverError($e, 'NavigationModuleController::store');
        }
    }

    /**
     * Return form data for editing an existing module.
     */
    public function edit(int $id, Request $request)
    {
        $module = $this->navigationModule->findOrFail($id);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(array_merge(
                ['navigation_module' => $module],
                $this->formPayload(excludeModuleId: $id)
            ));
        }
    }

    /**
     * Update a navigation module inside a DB transaction.
     *
     * Delegates to NavigationModule::saveNavigationModule(), commits, and returns
     * an HTTP 200 envelope for AJAX requests. Rolls back and returns a
     * server-error envelope on failure. Input is validated by {@see UpdateRequest}.
     *
     * @param UpdateRequest $request
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function update(UpdateRequest $request)
    {
        DB::beginTransaction();

        try {
            $this->navigationModule->saveNavigationModule($request->validated());

            DB::commit();

            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::ok('Navigation module updated successfully', Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::serverError($e, 'NavigationModuleController::update');
            }
        }
    }

    /**
     * Soft-delete or restore the specified resource.
     */
    public function destroy(DeleteRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $module = $this->navigationModule->withTrashed()->find($validated['id']);

            if ($module->trashed()) {
                $module->restore();
                $message = 'Restored';
            } else {
                $module->delete();
                $message = 'Deleted';
            }

            DB::commit();

            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::ok("Navigation module {$message} successfully", Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::serverError($e, 'NavigationModuleController::destroy');
            }
        }
    }

    /**
     * Build the shared form payload (navigations, permissions, parent modules, statuses).
     * Excludes a specific module from the parent-module list when editing.
     */
    private function formPayload(int $excludeModuleId = 0): array
    {
        return [
            'navigations'    => Navigation::select(['id', 'name'])
                ->where('status', 1)
                ->orderBy('name')
                ->get(),
            'permissions'    => Permission::select(['id', 'name'])
                ->orderBy('name')
                ->get(),
            'parent_modules' => NavigationModule::select(['id', 'name', 'navigation_id'])
                ->with('navigation:id,name')
                ->whereNull('ref_id')
                ->where('status', 1)
                ->when($excludeModuleId, fn ($q) => $q->where('id', '!=', $excludeModuleId))
                ->orderBy('navigation_id')
                ->orderBy('order_number')
                ->get(),
            'statuses'       => Status::list(),
        ];
    }
}
