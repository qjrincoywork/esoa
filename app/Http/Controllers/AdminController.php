<?php

namespace App\Http\Controllers;

// use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Requests\{UserCreateRequest, UserDeleteRequest, UserListRequest, UserUpdateRequest};
// use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{ Bus, DB, Log, Schema };
use Inertia\Inertia;
use Spatie\Permission\Models\{ Permission, Role };
use App\Jobs\{ ImportAccountsJob, ImportMainAccountsJob };

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

    public function startImport()
    {
        try {
            $this->importMainAccounts();
            $this->importAccounts();
        } catch (\Exception $e) {
            Log::error('startImport Jobs failed: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            throw $e;
        }
    }

    public function importAccounts()
    {
        DB::beginTransaction();

        try {
            DB::connection('hms')
                ->table('Accounts as a')
                ->leftJoin('agent_table as ag', 'a.ac_agcode', '=', 'ag.agent_code')
                ->orderBy('a.ac_id')
                ->select([
                    'a.*',
                    'ag.agent_id',
                    'ag.agent_code',
                    'ag.agent_name',
                ])
                ->chunk(2000, function ($chunk) {
                    Log::info('Start Account: ' . $chunk->count());
                    // dispatch job for each 2000 rows
                    ImportAccountsJob::dispatch($chunk);
                });

            DB::commit();
            Log::info('End Account Job');
        } catch (\Exception $e) {
            Log::error('Job failed Accounts: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollBack();
            throw $e;
        }
    }

    public function importMainAccounts()
    {
        DB::beginTransaction();

        try {
            DB::connection('hms')
                ->table('MainAcct')
                ->orderBy('ma_id')
                ->chunk(2000, function ($chunk) {
                    Log::info('Start Main Account: ' . $chunk->count());
                    // dispatch job for each 2000 rows
                    ImportMainAccountsJob::dispatch($chunk);
                });

            DB::commit();
            Log::info('End Main Account Job');
        } catch (\Exception $e) {
            Log::error('Job failed: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollBack();
            throw $e;
        }
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
        // dd('hits admin');
        // Start for roles & permissions
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

        // $user = User::find(1); // or find your user manually //620-office//1-home
        // $user->assignRole('admin');
        // End for roles & permissions
        // dd(DB::connection('soa')->select('SELECT TOP 1 name FROM sys.tables'));
        // $columns = DB::connection('soa')
        //     ->table('information_schema.columns')
        //     ->select('COLUMN_NAME')
        //     ->where('table_schema', DB::connection('soa')->getDatabaseName())
        //     ->where('table_name', 'Upload')
        //     ->pluck('COLUMN_NAME');
        
        // $result = DB::connection('cenuser')
        //     ->table('ref_gender')
        //     ->get();
        // $columns = DB::connection('soa')
            // ->table('Upload')
            // ->select('*')
            // ->where('up_id', 2)
            // ->get();
            $columns = DB::connection('soa')
            ->table('INFORMATION_SCHEMA.COLUMNS')
            ->select('COLUMN_NAME')
            ->where('TABLE_NAME', 'Upload')
            ->where('TABLE_SCHEMA', 'dbo')
            ->pluck('COLUMN_NAME');


        dd($columns);
        // dd($permissions, 'hits');
        // dd($this->users->getUsers([])->toArray());
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
