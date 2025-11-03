<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{ Suffix, User};
use App\Http\Requests\{UserCreateRequest, UserDeleteRequest, UserListRequest, UserUpdateRequest};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

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
    public function index(UserListRequest $request)
    {
        $users = $this->user->getUsers($request->validated())->toArray();
        // dd($users, auth()->user()->userDetail(), 'hits');
        // dd(Suffix::select(['id', 'name'])->get()->toArray(), 'hits user');
        return Inertia::render('users/Index', [
            'users' => $users,
            // 'users' => $this->user->getUsers($request->validated())->toArray()
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
    public function store(Request $request)
    {
        //
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
    public function edit(string $id)
    {
        // dd(Suffix::select(['id', 'name'])->get()->toArray(), 'edit');
        // 'suffixes' => Suffix::select(['id', 'name'])->get()->toArray(),
        $user = $this->user->with('userDetail')->findOrFail($id)->toArray();
        $suffixes = Suffix::select(['id', 'name'])->get()->toArray();

        // Return a partial response that the page can use to open the modal
        // return response()->json([
        //     'user'=> $user,
        //     'suffixes'=> $suffixes,
        // ]);
        return Inertia::render('users/Index', [
            'edit_user_payload' => [
                'user' => $user,
                'suffixes' => $suffixes,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = $this->user->with('userDetail')->findOrFail($id);

        $validated = $request->validate([
            'username' => ['nullable','string','max:255'],
            'email' => ['nullable','email','max:255'],
            'first_name' => ['nullable','string','max:255'],
            'middle_name' => ['nullable','string','max:255'],
            'last_name' => ['nullable','string','max:255'],
            'birthdate' => ['nullable','date'],
            'employee_no' => ['nullable','string','max:255'],
            'suffix_id' => ['nullable','integer'],
        ]);

        // Update user table fields
        $user->fill(array_filter([
            'username' => $validated['username'] ?? null,
            'email' => $validated['email'] ?? null,
        ], fn($v) => !is_null($v)));
        $user->save();

        // Update or create related detail
        $detailData = array_filter([
            'first_name' => $validated['first_name'] ?? null,
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'birthdate' => $validated['birthdate'] ?? null,
            'employee_no' => $validated['employee_no'] ?? null,
            'suffix_id' => $validated['suffix_id'] ?? null,
        ], fn($v) => !is_null($v));

        if (!empty($detailData)) {
            $user->userDetail()->updateOrCreate(['user_id' => $user->id], $detailData);
        }

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
