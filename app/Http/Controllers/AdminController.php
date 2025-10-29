<?php

namespace App\Http\Controllers;

// use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Requests\{UserCreateRequest, UserDeleteRequest, UserListRequest, UserUpdateRequest};
// use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\{ Permission, Role };

class AdminController extends Controller
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
    public function index(Request $request)
    {
        // try {
        //     return Inertia::render('dashboard');
        //     dd('hits');
        // } catch (\Exception $e) {
        //     dd($e->getMessage(), 'hits');
        // }
        dd('hits admin');
        // Role::firstOrCreate(['name' => 'admin']);
        // $user = User::find(620); // or find your user manually
        // $user->assignRole('admin');

        // $permissions = [
        //     'dashboard',
        //     'admin.index',
        //     'admin.store',
        //     'admin.create',
        //     'admin.edit',
        //     'users.index',
        //     'users.store',
        //     'users.create',
        //     'users.edit',
        // ];
        // foreach ($permissions as $key => $value) {
        //     if (!Permission::where(['name' => $value])->exists()) {
        //         Permission::create(['name' => $value]);
        //     }
        // }
        // // Create roles
        // $admin = Role::create(['name' => 'admin']);
        // $editor = Role::create(['name' => 'editor']);
        // $viewer = Role::create(['name' => 'viewer']);

        // // Assign permissions to roles
        // $admin->givePermissionTo(Permission::all());
        // $editor->givePermissionTo(['dashboard', 'admin.index']);
        // $viewer->givePermissionTo(['dashboard']);

        // $user = User::find(620); // or find your user manually
        // $user->assignRole('admin');
        dd($permissions, 'hits');
        dd($this->users->getUsers([])->toArray());
        // return Inertia::render('users/Index', [
        //     'users' => $this->users->getUsers([])->toArray()
        //     // 'users' => $this->users->getUsers($request->validated())->toArray()
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
