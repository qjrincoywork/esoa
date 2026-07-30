<?php

namespace App\Http\Controllers;

use App\Enums\{
    AccountStatus,
    AccountType,
    Gender,
    Server,
    UserImportColumn,
    UserType
};
use App\Helpers\CustomResponse;
use App\Helpers\SqlDatabase;
use App\Http\Requests\User\AccountAccessUsersRequest;
use App\Http\Requests\User\BulkDestroyRequest;
use App\Http\Requests\User\BulkStoreRequest;
use App\Http\Requests\User\BulkToggleActiveRequest;
use App\Http\Requests\User\BulkUpdateRoleRequest;
use App\Http\Requests\User\BulkUserVerificationRequest;
use App\Http\Requests\User\CreateRequest;
use App\Http\Requests\User\DeleteRequest;
use App\Http\Requests\User\ListRequest;
use App\Http\Requests\User\ToggleActiveRequest;
use App\Http\Requests\User\UpdateRequest;
use App\Http\Requests\User\UpdateRoleRequest;
use App\Http\Requests\User\VerifyRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\BranchResource;
use App\Http\Resources\CommonResource;
use App\Http\Resources\UserAccessResource;
use App\Http\Resources\UserBulkImportResultResource;
use App\Http\Resources\UserListResource;
use App\Mail\UserWelcome;
use App\Models\Account;
use App\Models\Citizenship;
use App\Models\CivilStatus;
use App\Models\Department;
use App\Models\Position;
use App\Models\Suffix;
use App\Models\User;
use App\Services\UserBulkImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

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
     * Injects the User model and stores the SqlDatabase class name for
     * on-demand HMS lookups.
     *
     *
     * @return void
     */
    public function __construct(User $user)
    {
        $this->user = $user;
        $this->sqlDatabase = SqlDatabase::class;
    }

    /**
     * Render the Inertia "users/Index" page with a filtered user list and filter options.
     *
     * Also passes the user-type and department option lists used by the filter UI.
     * Filters are validated by {@see ListRequest}.
     *
     * @return \Inertia\Response
     */
    public function index(ListRequest $request)
    {
        $users = $this->user->getUsers($request->validated());

        return Inertia::render('users/Index', [
            'users' => new CommonResource(UserListResource::collection($users)),
            'filter_options' => [
                'user_types' => UserType::list(),
                'departments' => Department::select(['id', 'name'])->get()->toArray(),
            ],
        ]);
    }

    /**
     * Return the lookup lists required to build the "Create User" form (AJAX only).
     *
     * Responds with account-type/user-type/gender option lists plus civil
     * statuses, citizenships, departments, positions and all roles, so the form
     * can be populated without a full page navigation. Non-AJAX requests fall
     * through and receive no content.
     *
     * @return \Illuminate\Http\JsonResponse|void
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
                'all_roles' => Role::query()->get(['id', 'name', 'guard_name']),
            ]);
        }
    }

    /**
     * Return the metadata needed to drive the bulk-import modal (AJAX only).
     *
     * Surfaces the canonical template columns plus the accepted values for the
     * name-based columns (user types, genders, civil statuses, citizenships and
     * roles) so the client can build a template, guide the user, and validate
     * headers before uploading. Non-AJAX requests fall through with no content.
     *
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function bulkCreate(Request $request)
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'columns' => UserImportColumn::ordered(),
                'required_columns' => UserImportColumn::required(),
                'types' => UserType::list(),
                'genders' => \App\Models\Gender::orderBy('name')->pluck('name'),
                'civil_statuses' => CivilStatus::orderBy('name')->pluck('name'),
                'citizenships' => Citizenship::orderBy('name')->pluck('name'),
                'roles' => Role::query()->orderBy('name')->pluck('name'),
            ]);
        }
    }

    /**
     * Import many users at once from parsed spreadsheet rows.
     *
     * Delegates per-row validation, name/branch resolution and persistence to
     * {@see UserBulkImportService}, which creates each valid row in its own
     * transaction and reports failures individually. Returns a summary envelope
     * (counts plus per-row errors) so partial successes are surfaced to the user.
     * Credentials are emailed on verification, not here — mirroring store().
     * Input is validated by {@see BulkStoreRequest}.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkStore(BulkStoreRequest $request, UserBulkImportService $service)
    {
        try {
            $result = $service->import($request->validated()['users']);

            $created = $result['created'];
            $failed = $result['failed'];
            $message = $failed === 0
                ? ($created === 1 ? '1 user imported successfully' : "{$created} users imported successfully")
                : "Imported {$created} of {$result['total']} rows; {$failed} could not be imported.";

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'result' => new UserBulkImportResultResource($result),
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return CustomResponse::serverError($e, 'UserController::bulkStore');
        }
    }

    /**
     * Persist a new user and sync their roles inside a DB transaction.
     *
     * Delegates creation to User::saveUser(), assigns any supplied roles, and
     * commits. Login credentials are intentionally NOT emailed here — they are
     * issued when the user is verified (see verify()). Returns an HTTP 201
     * envelope for AJAX requests; rolls back and returns a server-error envelope
     * on failure. Input is validated by {@see CreateRequest}.
     *
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function store(CreateRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $user = $this->user->saveUser($validated);
            $this->syncRoles($user, $validated['roles'] ?? []);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return CustomResponse::serverError($e, 'UserController');
        }

        // Credentials are emailed on verification, not here — see verify().
        if ($request->wantsJson() || $request->ajax()) {
            return CustomResponse::created('User Created successfully', Response::HTTP_CREATED);
        }
    }

    /**
     * Unused resource stub; users are not shown individually via this action.
     *
     * @return void
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Return HMS accounts matching the request params as JSON (AJAX only).
     *
     * Queries the HMS server via {@see SqlDatabase} to feed account-picker
     * comboboxes on the user forms. Non-AJAX requests fall through and receive
     * no content.
     *
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function getAccounts(Request $request)
    {
        $accounts = (new $this->sqlDatabase(Server::HMS))->getAccountsByParams($request->all());

        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'accounts' => new CommonResource(AccountResource::collection($accounts)),
            ]);
        }
    }

    /**
     * Return HMS branches matching the request params as JSON (AJAX only).
     *
     * Queries the HMS server via {@see SqlDatabase} to feed branch-picker
     * comboboxes on the user forms. Non-AJAX requests fall through and receive
     * no content.
     *
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function getBranches(Request $request)
    {
        $branches = (new $this->sqlDatabase(Server::HMS))->getBranchesByParams($request->all());

        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'branches' => new CommonResource(BranchResource::collection($branches)),
            ]);
        }
    }

    /**
     * Return users that already have account/branch access, so their access
     * can be copied onto another user. Supports search (by username/email)
     * and pagination, and can exclude the user currently being edited.
     */
    public function accountAccessUsers(AccountAccessUsersRequest $request)
    {
        $validated = $request->validated();
        $search = $validated['name'] ?? null;
        $excludeId = $validated['exclude_id'] ?? null;
        $perPage = $validated['per_page'] ?? config('vc.default_pages');

        $users = $this->user
            ->whereHas('userAccounts')
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->when($search, function ($query) use ($search) {
                $query->where(fn ($q) => $q
                    ->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->with('userAccounts')
            ->orderBy('username')
            ->paginate($perPage);

        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'users' => new CommonResource(UserAccessResource::collection($users)),
            ]);
        }
    }

    /**
     * Return the specified user and lookup lists for the edit form (AJAX only).
     *
     * Resolves the user with their detail, account access and roles, and includes
     * suffix/gender/user-type/account-type options plus civil statuses,
     * citizenships, departments, positions and all roles. Non-AJAX requests fall
     * through and receive no content.
     *
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function edit(int $id, Request $request)
    {
        $user = $this->user->with(['userDetail', 'userAccounts', 'roles:id,name,guard_name'])->findOrFail($id)->toArray();
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
                'all_roles' => Role::query()->get(['id', 'name', 'guard_name']),
            ]);
        }
    }

    /**
     * Update the specified user and re-sync their roles inside a DB transaction.
     *
     * Resolves the target user by id, delegates the update to User::saveUser(),
     * re-syncs any supplied roles, and commits. Returns an HTTP 200 envelope for
     * AJAX requests; rolls back and returns a server-error envelope on failure.
     * Input is validated by {@see UpdateRequest}.
     *
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function update(UpdateRequest $request)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();
            $target = User::findOrFail($validated['id']);
            $this->user->saveUser($validated, $target);
            $this->syncRoles($target, $validated['roles'] ?? []);

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
     * Toggle soft-delete state for the specified user (delete or restore).
     *
     * Runs inside a DB transaction: resolves the user including trashed rows,
     * restores them if already trashed or soft-deletes them otherwise, then
     * commits and returns an HTTP 200 envelope for AJAX requests. Rolls back and
     * returns a server-error envelope on failure. Input is validated by
     * {@see DeleteRequest}.
     *
     * @return \Illuminate\Http\JsonResponse|void
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
                return CustomResponse::ok('User '.$message.' successfully', Response::HTTP_OK);
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
     * Verify one or more users: marks them verified/approved, issues a fresh
     * temporary password for each, and emails their login credentials.
     * Restricted to superadmin via VerifyRequest::authorize() and route middleware.
     */
    public function verify(VerifyRequest $request)
    {
        $ids = $request->validated()['ids'];

        try {
            $users = $this->user->whereIn('id', $ids)->get();
            $count = $this->issueCredentialsAndNotify($users, markVerified: true);

            $message = $count === 1
                ? 'User verified and credentials emailed successfully'
                : "{$count} users verified and credentials emailed successfully";

            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::ok($message, Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::serverError($e, 'UserController::verify');
            }
        }
    }

    /**
     * Bulk-send login credentials (and a fresh temporary password) to the
     * selected users, marking them verified/approved in the process.
     *
     * Useful for (re)issuing credentials to a batch of users at once — e.g.
     * when a temporary password has expired or the welcome email was missed.
     */
    public function bulkUserVerification(BulkUserVerificationRequest $request)
    {
        $ids = $request->validated()['user_ids'];

        try {
            $users = $this->user->whereIn('id', $ids)->get();
            $count = $this->issueCredentialsAndNotify($users, markVerified: true);

            $message = $count === 1
                ? 'Credentials emailed to 1 user successfully'
                : "Credentials emailed to {$count} users successfully";

            return CustomResponse::ok($message, Response::HTTP_OK);
        } catch (\Exception $e) {
            return CustomResponse::serverError($e, 'UserController::bulkUserVerification');
        }
    }

    /**
     * Sync a user's roles by ID and refresh the permission cache.
     *
     * No-op when no role IDs are supplied, so callers that don't manage roles
     * (e.g. a profile edit) leave existing assignments untouched.
     *
     * @param  array<int, int|string>  $roleIds
     */
    private function syncRoles(User $user, array $roleIds): void
    {
        if (empty($roleIds)) {
            return;
        }

        $user->roles()->sync($roleIds);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * (Re)issue a temporary password for each user, optionally flag them as
     * verified/approved, and email their credentials.
     *
     * Passwords are reset inside a single transaction; emails are dispatched
     * only after it commits so a delivery failure never rolls back the reset.
     *
     * @param  \Illuminate\Support\Collection<int, User>  $users
     * @return int Number of users notified.
     */
    private function issueCredentialsAndNotify($users, bool $markVerified = false): int
    {
        $deliveries = [];

        DB::beginTransaction();

        try {
            foreach ($users as $user) {
                $plainPassword = $user->withTemporaryPassword();

                if ($markVerified) {
                    $user->email_verified_at = now();
                    $user->is_approved = true;
                }

                $user->save();
                $deliveries[] = ['user' => $user, 'password' => $plainPassword];
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        foreach ($deliveries as $delivery) {
            $this->sendWelcomeMail($delivery['user'], $delivery['password']);
        }

        return count($deliveries);
    }

    /**
     * Send the welcome email carrying a user's login credentials.
     * Failures are logged and swallowed so one bad address never aborts a batch.
     */
    private function sendWelcomeMail(User $user, string $plainPassword): void
    {
        try {
            $user->loadMissing('userDetail');

            $expiresAt = $user->temporary_password_expires_at
                ?->format('F j, Y \a\t h:i A');

            Mail::to($user->email)->send(new UserWelcome(
                $user,
                $plainPassword,
                $expiresAt ?? '',
            ));
        } catch (\Throwable $e) {
            Log::error('UserWelcome mail failed', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
            ]);
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
     * Return all available roles (used by bulk role assignment).
     */
    public function allRoles(Request $request)
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'all_roles' => Role::query()->get(['id', 'name', 'guard_name']),
            ]);
        }
    }

    /**
     * Sync the same set of roles across multiple users at once.
     */
    public function bulkUpdateRoles(BulkUpdateRoleRequest $request)
    {
        $validated = $request->validated();
        $userIds = $validated['user_ids'];
        $roleIds = $validated['roles'] ?? [];

        DB::beginTransaction();

        try {
            $users = $this->user->whereIn('id', $userIds)->get();

            foreach ($users as $user) {
                $user->roles()->sync($roleIds);
            }

            DB::commit();

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $count = count($userIds);
            $message = $count === 1
                ? 'Roles updated for 1 user successfully'
                : "Roles updated for {$count} users successfully";

            return CustomResponse::ok($message, Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();

            return CustomResponse::serverError($e, 'UserController::bulkUpdateRoles');
        }
    }

    /**
     * Set the is_active status for multiple users at once.
     */
    public function bulkToggleActive(BulkToggleActiveRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $this->user->whereIn('id', $validated['user_ids'])
                ->update(['is_active' => $validated['is_active']]);

            DB::commit();

            $count = count($validated['user_ids']);
            $label = $validated['is_active'] ? 'activated' : 'deactivated';
            $message = $count === 1
                ? "User {$label} successfully"
                : "{$count} users {$label} successfully";

            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::ok($message, Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::serverError($e, 'UserController::bulkToggleActive');
            }
        }
    }

    /**
     * Soft-delete or restore multiple users at once.
     */
    public function bulkDestroy(BulkDestroyRequest $request)
    {
        $validated = $request->validated();
        $isRestore = $validated['action'] === 'restore';

        DB::beginTransaction();

        try {
            if ($isRestore) {
                $this->user->withTrashed()
                    ->whereIn('id', $validated['user_ids'])
                    ->whereNotNull('deleted_at')
                    ->restore();
            } else {
                $this->user->whereIn('id', $validated['user_ids'])
                    ->whereNull('deleted_at')
                    ->delete();
            }

            DB::commit();

            $count = count($validated['user_ids']);
            $label = $isRestore ? 'restored' : 'deleted';
            $message = $count === 1
                ? "User {$label} successfully"
                : "{$count} users {$label} successfully";

            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::ok($message, Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::serverError($e, 'UserController::bulkDestroy');
            }
        }
    }

    /**
     * Re-sync a single user's roles (by id) and flush the permission cache.
     *
     * Runs inside a DB transaction: resolves the user, syncs to the given role
     * ids (clearing all when none supplied) to avoid name/guard resolution
     * issues, forgets Spatie's cached permissions, commits, and returns an
     * HTTP 200 envelope. Rolls back and returns a server-error envelope on
     * failure. Input is validated by {@see UpdateRoleRequest}.
     *
     * @return \Illuminate\Http\JsonResponse
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
