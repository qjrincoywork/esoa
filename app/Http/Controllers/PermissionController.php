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
     * Render the Inertia "permissions/Index" page with a paginated permission list.
     *
     * Supports optional name/guard_name LIKE filters and configurable sort column
     * and direction (defaults: name asc), maps each row to id/name/guard_name plus
     * a human-readable created_at, and preserves the query string across pages.
     * Filters are validated by {@see ListRequest}.
     *
     * @param ListRequest $request
     * @return \Inertia\Response
     */
    public function index(ListRequest $request)
    {
        $validated = $request->validated();
        $perPage = $validated['per_page'] ?? config('vc.default_pages');
        $sortBy = $validated['sort_by'] ?? 'name';
        $sortDirection = $validated['sort_direction'] ?? 'asc';

        $permissions = $this->permission->query()
            ->when(!empty($validated['name']), fn ($q) =>
                $q->where('name', 'LIKE', '%' . $validated['name'] . '%')
            )
            ->when(!empty($validated['guard_name']), fn ($q) =>
                $q->where('guard_name', 'LIKE', '%' . $validated['guard_name'] . '%')
            )
            ->orderBy($sortBy, $sortDirection)
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
     * Unused resource stub; the create form is rendered client-side.
     *
     * @return void
     */
    public function create()
    {
        //
    }

    /**
     * Persist a new permission inside a DB transaction.
     *
     * Creates the permission from validated input, commits, and returns an
     * HTTP 201 envelope. Rolls back and returns a server-error envelope on
     * failure. Input is validated by {@see CreateRequest}.
     *
     * @param CreateRequest $request
     * @return \Illuminate\Http\JsonResponse
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

            return CustomResponse::serverError($e, 'PermissionController');
        }
    }

    /**
     * Unused resource stub; permissions are not shown individually.
     *
     * @param string $id
     * @return void
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Return the specified permission as JSON for the edit form (AJAX only).
     *
     * Non-AJAX requests fall through and receive no content.
     *
     * @param string $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
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
     * Update the specified permission inside a DB transaction.
     *
     * Resolves the permission by id, applies validated changes, commits, and
     * returns an HTTP 200 envelope for AJAX requests. Rolls back and returns a
     * server-error envelope on failure. Input is validated by {@see UpdateRequest}.
     *
     * @param UpdateRequest $request
     * @return \Illuminate\Http\JsonResponse|void
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
                return CustomResponse::serverError($e, 'PermissionController::update');
            }
        }
    }

    /**
     * Permanently delete the specified permission inside a DB transaction.
     *
     * Resolves the permission by id and hard-deletes it (the Spatie permission
     * model is not soft-deletable), commits, and returns an HTTP 200 JSON message
     * for AJAX requests. Rolls back and returns a server-error envelope on
     * failure. Input is validated by {@see DeleteRequest}.
     *
     * @param DeleteRequest $request
     * @return \Illuminate\Http\JsonResponse|void
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

            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::serverError($e, 'PermissionController');
            }
        }
    }
}
