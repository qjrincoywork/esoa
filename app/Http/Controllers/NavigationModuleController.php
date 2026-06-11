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

    public function __construct(NavigationModule $navigationModule)
    {
        $this->navigationModule = $navigationModule;
    }

    /**
     * Display a listing of the resource.
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
     * Store a newly created resource in storage.
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

            return CustomResponse::error($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
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
     * Update the specified resource in storage.
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
                return CustomResponse::error($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
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
                return CustomResponse::error($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
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
