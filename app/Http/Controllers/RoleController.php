<?php

namespace App\Http\Controllers;

use App\Helpers\CustomResponse;
use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Http\Requests\Role\{DeleteRequest, ListRequest, CreateRequest, UpdatePermissionsRequest, UpdateRequest};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class RoleController extends Controller
{
    /**
     * Role model instance.
     *
     * @var Role
     */
    protected $role;

    /**
     * RoleController constructor.
     *
     * @param Role $role
     *
     * @return void
     */
    public function __construct(Role $role)
    {
        $this->role = $role;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(ListRequest $request)
    {
        $params = $request->validated();
        $perPage = $params['per_page'] ?? config('vc.default_pages');

        $roles = $this->role
            ->when(isset($params['search_string']), function ($query) use ($params) {
                $query->where('name', 'LIKE', '%' . $params['search_string'] . '%');
            })
            ->orderBy('id', 'desc')
            ->with('permissions');

        return Inertia::render('roles/Index', [
            'roles' => $roles->paginate($perPage),
            'permissions' => Permission::all(),
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
            $role = $this->role->create($validated);

            if ($request->has('permissions')) {
                $role->syncPermissions($request->permissions);
            }
            // Commit transaction
            DB::commit();

            return CustomResponse::created('Role Created successfully', Response::HTTP_CREATED);
        } catch (\Exception $e) {
            // Catch and handle any unexpected errors
            DB::rollBack();

            return CustomResponse::serverError($e, 'RoleController::store');
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
        $role = Role::find($id);

        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'role' => $role,
            ]);
        }
    }

    /**
     * Show the permissions assigned to the specified role.
     *
     * Returns the role's current permissions as JSON for AJAX consumers,
     * used by the role-permission management UI to pre-populate selected
     * permissions for a given role.
     *
     * @param  string  $id       The ID of the role.
     * @param  \Illuminate\Http\Request  $request  The incoming HTTP request.
     * @return \Illuminate\Http\JsonResponse|null  JSON response for AJAX requests, or null otherwise.
     */
    public function editPermissions(string $id, Request $request)
    {
        $role = Role::with('permissions')->find($id);

        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'access_permissions' => $role->permissions,
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
            $role = $this->role->find($validated['id']);
            $role->update($validated);

            // Commit transaction
            DB::commit();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::created('Role Updated successfully', Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            // Catch and handle any unexpected errors
            DB::rollBack();

            return CustomResponse::serverError($e, 'RoleController::update');
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
            $role = $this->role->find($validated['id']);
            $role->delete();

            // Commit transaction
            DB::commit();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::created('Role Deleted successfully', Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            // Catch and handle any unexpected errors
            DB::rollBack();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::serverError($e, 'RoleController::destroy');
            }
        }
    }

    /**
     * Update the permissions assigned to a role.
     */
    public function updatePermissions(UpdatePermissionsRequest $request)
    {
        $validated = $request->validated();
        DB::beginTransaction();

        try {
            $role = $this->role->findOrFail($validated['role_id']);
            $role->syncPermissions($validated['permissions'] ?? []);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            DB::commit();

            return CustomResponse::created('Role permissions updated successfully', Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();

            return CustomResponse::serverError($e, 'RoleController::updatePermissions');
        }
    }
}
