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
     * Render the Inertia "roles/Index" page with paginated roles and all permissions.
     *
     * Supports an optional name LIKE filter via `search_string`, eager-loads each
     * role's permissions, orders by newest id first, and also passes the full
     * permission list for the assignment UI. Filters are validated by
     * {@see ListRequest}.
     *
     * @param ListRequest $request
     * @return \Inertia\Response
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
     * Unused resource stub; the create form is rendered client-side.
     *
     * @return void
     */
    public function create()
    {
        //
    }

    /**
     * Persist a new role (and optionally sync its permissions) in a DB transaction.
     *
     * Creates the role from validated input, syncs the given permissions when a
     * `permissions` array is supplied, commits, and returns an HTTP 201 envelope.
     * Rolls back and returns a server-error envelope on failure. Input is
     * validated by {@see CreateRequest}.
     *
     * @param CreateRequest $request
     * @return \Illuminate\Http\JsonResponse
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
     * Unused resource stub; roles are not shown individually.
     *
     * @param string $id
     * @return void
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Return the specified role as JSON for the edit form (AJAX only).
     *
     * Non-AJAX requests fall through and receive no content.
     *
     * @param string $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
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
     * Update the specified role inside a DB transaction.
     *
     * Resolves the role by id, applies validated changes (name/attributes only;
     * permissions are handled by updatePermissions()), commits, and returns an
     * HTTP 200 envelope for AJAX requests. Rolls back and returns a server-error
     * envelope on failure. Input is validated by {@see UpdateRequest}.
     *
     * @param UpdateRequest $request
     * @return \Illuminate\Http\JsonResponse|void
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
     * Delete the specified role inside a DB transaction.
     *
     * Resolves the role by id and deletes it, commits, and returns an HTTP 200
     * envelope for AJAX requests. Rolls back and returns a server-error envelope
     * on failure. Input is validated by {@see DeleteRequest}.
     *
     * @param DeleteRequest $request
     * @return \Illuminate\Http\JsonResponse|void
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
     * Sync the permissions assigned to a role and flush the permission cache.
     *
     * Runs inside a DB transaction: resolves the role by id, syncs it to the
     * given permission set (clearing all when none supplied), forgets Spatie's
     * cached permissions, commits, and returns an HTTP 200 envelope. Rolls back
     * and returns a server-error envelope on failure. Input is validated by
     * {@see UpdatePermissionsRequest}.
     *
     * @param UpdatePermissionsRequest $request
     * @return \Illuminate\Http\JsonResponse
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
