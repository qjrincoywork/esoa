<?php

namespace App\Http\Controllers;

use App\Enums\{ AccountType, Gender, Server, UserType };
use App\Helpers\CustomResponse;
use App\Helpers\SqlDatabase;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\{CreateRequest, DeleteRequest, ListRequest, ToggleActiveRequest, UpdateRequest, UpdateRoleRequest, VerifyRequest};
use App\Http\Resources\AccountResource;
use App\Http\Resources\BranchResource;
use App\Http\Resources\CommonResource;
use App\Http\Resources\UserListResource;
use App\Models\{Account, Citizenship, CivilStatus, Department, Position, Suffix, User };
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * User model instance.
     *
     * @var User
     */
    protected $user;

    /**
     * SqlDatabase instance.
     *
     * @var SqlDatabase
     */
    protected $sqlDatabase;

    /**
     * UserController constructor.
     *
     * @param User $user
     * @param SqlDatabase $sqlDatabase
     *
     * @return void
     */
    public function __construct(User $user)
    {
        $this->user = $user;
        $this->sqlDatabase = SqlDatabase::class;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(ListRequest $request)
    {
        $users = $this->user->getUsers($request->validated());

        return Inertia::render('users/Index', [
            'users' => new CommonResource(UserListResource::collection($users)),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $civil_statuses = CivilStatus::select(['id', 'name'])->get()->toArray();
        $citizenships = Citizenship::select(['id', 'name'])->get()->toArray();
        $departments = Department::select(['id', 'name'])->get()->toArray();
        $positions = Position::select(['id', 'name'])->get()->toArray();

        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'account_types' => AccountType::list(),
                'types' => UserType::list(),
                'genders' => Gender::list(),
                'civil_statuses' => $civil_statuses,
                'citizenships' => $citizenships,
                'departments' => $departments,
                'positions' => $positions,
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
            $this->user->saveUser($validated);

            // Commit transaction
            DB::commit();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::created('User Created successfully', Response::HTTP_CREATED);
            }
        } catch (\Exception $e) {
            // Catch and handle any unexpected errors
            DB::rollBack();

            return CustomResponse::serverError($e, 'UserController');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    public function getAccounts(Request $request)
    {
        $accounts = (new $this->sqlDatabase(Server::HMS))->getAccountsByParams($request->all());

        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'accounts' => new CommonResource(AccountResource::collection($accounts))
            ]);
        }
    }

    public function getBranches(Request $request)
    {
        $branches = (new $this->sqlDatabase(Server::HMS))->getBranchesByParams($request->all());

        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'branches' => new CommonResource(BranchResource::collection($branches))
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id, Request $request)
    {
        $user = $this->user->with('userDetail')->findOrFail($id)->toArray();
        $suffixes = Suffix::select(['id', 'name'])->get()->toArray();
        $civil_statuses = CivilStatus::select(['id', 'name'])->get()->toArray();
        $citizenships = Citizenship::select(['id', 'name'])->get()->toArray();
        $departments = Department::select(['id', 'name'])->get()->toArray();
        $positions = Position::select(['id', 'name'])->get()->toArray();

        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'user' => $user,
                'suffixes' => $suffixes,
                'genders' => Gender::list(),
                'types' => UserType::list(),
                'account_types' => AccountType::list(),
                'civil_statuses' => $civil_statuses,
                'citizenships' => $citizenships,
                'departments' => $departments,
                'positions' => $positions,
            ]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRequest $request)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();
            $target = User::findOrFail($validated['id']);
            $this->user->saveUser($validated, $target);

            DB::commit();

            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::ok('User Updated successfully', Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::serverError($e, 'UserController::update');
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
            $user = $this->user
                ->withTrashed()
                ->find($validated['id']);

            if ($user->trashed()) {
                $user->restore();
                $message = 'Restored';
            } else {
                $user->delete();
                $message = 'Deleted';
            }

            // Commit transaction
            DB::commit();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::ok('User ' . $message . ' successfully', Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            // Catch and handle any unexpected errors
            DB::rollBack();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::serverError($e, 'UserController');
            }
        }
    }

    /**
     * Return the roles currently assigned to a user and the full roles list.
     *
     * Used by the user-role assignment modal.
     */
    public function editRoles(int $id, Request $request)
    {
        $user = $this->user->with('roles')->findOrFail($id);

        // Only return JSON for AJAX calls (matches your modal pattern)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'user_roles' => $user->roles->map(fn ($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'guard_name' => $role->guard_name,
                ]),
                'all_roles' => Role::query()->get(['id', 'name', 'guard_name']),
            ]);
        }

        return response()->json([
            'user_roles' => $user->roles,
            'all_roles' => Role::query()->get(['id', 'name', 'guard_name']),
        ]);
    }

    /**
     * Verify one or more users: sets email_verified_at and is_approved.
     * Restricted to superadmin via VerifyRequest::authorize() and route middleware.
     */
    public function verify(VerifyRequest $request)
    {
        $ids = $request->validated()['ids'];

        DB::beginTransaction();

        try {
            $this->user->whereIn('id', $ids)->update([
                'email_verified_at' => now(),
                'is_approved'       => 1,
            ]);

            DB::commit();

            $count   = count($ids);
            $message = $count === 1
                ? 'User verified successfully'
                : "{$count} users verified successfully";

            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::ok($message, Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::serverError($e, 'UserController');
            }
        }
    }

    /**
     * Set the is_active status for a single user.
     * Accepts an explicit is_active value so the client drives the state
     * rather than relying on a server-side read-then-flip.
     */
    public function toggleActive(ToggleActiveRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $target = User::findOrFail($validated['id']);
            $target->update(['is_active' => $validated['is_active']]);

            DB::commit();

            $label = $validated['is_active'] ? 'activated' : 'deactivated';

            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::ok("User {$label} successfully", Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::serverError($e, 'UserController::toggleActive');
            }
        }
    }

    /**
     * Save role assignment changes for a user.
     */
    public function updateRoles(UpdateRoleRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $user = $this->user->findOrFail($validated['user_id']);

            // Sync by role IDs to avoid name/guard resolution issues.
            $user->roles()->sync($validated['roles'] ?? []);

            DB::commit();

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return CustomResponse::created('User roles updated successfully', Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();

            return CustomResponse::serverError($e, 'UserController');
        }
    }
}
