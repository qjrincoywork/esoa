<?php

namespace App\Http\Controllers;

use App\Enums\{ AccountType, Gender, Server };
use App\Helpers\CustomResponse;
use App\Helpers\SqlDatabase;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\{CreateRequest, DeleteRequest, ListRequest, UpdateRequest};
use App\Http\Resources\AccountResource;
use App\Http\Resources\BranchResource;
use App\Http\Resources\CommonResource;
use App\Models\{Account, Citizenship, CivilStatus, Department, Position, Suffix, User };
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
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
        $users = $this->user->getUsers($request->validated())->toArray();

        return Inertia::render('users/Index', [
            'users' => $users,
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
        dd($request->validated());

        DB::beginTransaction();

        try {
            $this->user->saveUser($request->validated());

            // Commit transaction
            DB::commit();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::ok('User Updated successfully', Response::HTTP_OK);
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
                return CustomResponse::error($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
    }
}
