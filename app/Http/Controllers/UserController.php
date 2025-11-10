<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{ Citizenship, CivilStatus, Department, Gender, Position, Suffix, User };
use App\Http\Requests\User\{CreateRequest, DeleteRequest, ListRequest, UpdateRequest};
use Illuminate\Http\JsonResponse;
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
     * UserController constructor.
     *
     * @param User $user
     *
     * @return void
     */
    public function __construct(User $user)
    {
        $this->user = $user;
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
        $suffixes = Suffix::select(['id', 'name'])->get()->toArray();
        $genders = Gender::select(['id', 'name'])->get()->toArray();
        $civil_statuses = CivilStatus::select(['id', 'name'])->get()->toArray();
        $citizenships = Citizenship::select(['id', 'name'])->get()->toArray();
        $departments = Department::select(['id', 'name'])->get()->toArray();
        $positions = Position::select(['id', 'name'])->get()->toArray();

        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'suffixes' => $suffixes,
                'genders' => $genders,
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
            // DB::commit();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'User Created successfully'
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
        $user = $this->user->with('userDetail')->findOrFail($id)->toArray();
        $suffixes = Suffix::select(['id', 'name'])->get()->toArray();
        $genders = Gender::select(['id', 'name'])->get()->toArray();
        $civil_statuses = CivilStatus::select(['id', 'name'])->get()->toArray();
        $citizenships = Citizenship::select(['id', 'name'])->get()->toArray();
        $departments = Department::select(['id', 'name'])->get()->toArray();
        $positions = Position::select(['id', 'name'])->get()->toArray();

        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'user' => $user,
                'suffixes' => $suffixes,
                'genders' => $genders,
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
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $task = $this->user->saveUser($validated);

            // Commit transaction
            DB::commit();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'User Updated successfully'
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

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeleteRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $task = $this->user->saveUser($validated);

            // Commit transaction
            // DB::commit();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'User Deleted successfully'
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
