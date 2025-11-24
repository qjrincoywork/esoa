<?php

namespace App\Http\Controllers;

use App\Helpers\CustomResponse;
use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Permission;
use App\Http\Requests\Permission\{CreateRequest, DeleteRequest, ListRequest, UpdateRequest};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class PermissionController extends Controller
{
    /**
     * Permission model instance.
     *
     * @var Permission
     */
    protected $permission;

    /**
     * PermissionController constructor.
     *
     * @param Permission $permission
     *
     * @return void
     */
    public function __construct(Permission $permission)
    {
        $this->permission = $permission;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(ListRequest $request)
    {
        $perPage = $params['per_page'] ?? config('vc.default_pages');

        $permissions = $this->permission->query()
            ->with('permissions')
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(function ($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'guard_name' => $permission->guard_name,
                    'created_at' => $permission->created_at?->diffForHumans(),
                ];
            });

        return Inertia::render('permissions/Index', [
            'permissions' => $permissions,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $this->permission->create($validated);

            // Commit transaction
            DB::commit();

            return CustomResponse::created('Role Created successfully', Response::HTTP_CREATED);
        } catch (\Exception $e) {
            // Catch and handle any unexpected errors
            DB::rollBack();

            return CustomResponse::error($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id, Request $request)
    {
        $permission = Permission::find($id);

        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'permission' => $permission,
            ]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $permission = $this->permission->find($validated['id']);
            $permission->update($validated);

            // Commit transaction
            DB::commit();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::created('Permission Updated successfully', Response::HTTP_OK);
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

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeleteRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $permission = $this->permission->find($validated['id']);
            $permission->delete();

            // Commit transaction
            DB::commit();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'Permission Deleted successfully'
                ], Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            // Catch and handle any unexpected errors
            DB::rollBack();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'message' => $e->getMessage()
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
    }
}
