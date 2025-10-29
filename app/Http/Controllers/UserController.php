<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Requests\{UserCreateRequest, UserDeleteRequest, UserListRequest, UserUpdateRequest};
use Illuminate\Http\JsonResponse;
// use Illuminate\Http\Request;
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
    public function __construct(User $user) {
        $this->user = $user;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = $this->user->getUsers([])->toArray();
        // dd($this->users->getUsers([])->toArray(), 'hits user');
        dd('hits user index');
        // return Inertia::render('users/Index', [
        //     'users' => $this->user->getUsers([])->toArray()
        //     // 'users' => $this->user->getUsers($request->validated())->toArray()
        // ]);
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
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
