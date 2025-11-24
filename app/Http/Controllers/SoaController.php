<?php

namespace App\Http\Controllers;

use App\Helpers\CustomResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\{CreateRequest, DeleteRequest, ListRequest, UpdateRequest};
use App\Models\{Account, Citizenship, CivilStatus, Contact, Department, Gender, MainAccount, Position, Suffix, User };
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SoaController extends Controller
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
    public function index(Request $request)
    {
        // $columns = DB::connection('soa')
        // ->table('INFORMATION_SCHEMA.COLUMNS')
        // ->select('COLUMN_NAME')
        // ->where('TABLE_NAME', 'Upload')
        // ->where('TABLE_SCHEMA', 'dbo')
        // ->pluck('COLUMN_NAME');
        DB::beginTransaction();

        try {
            $authId = auth()->id();
            $mainAccounts = DB::connection('hms')
                ->table('MainAcct')
                ->get()
                ->toArray();
    
            $mainAccntArr = [];
            $contactArr = [];
            foreach ($mainAccounts as $key => $mainAccount) {
                $contactArr = [
                    'created_by'=> $authId,
                    'name'=> $mainAccount->ma_contactperson,
                    'number'=> $mainAccount->ma_contactno,
                ];
                // $contact = Contact::create($contactArr);
    
                $mainAccntArr = [
                    'code'=> $mainAccount->ma_code,
                    'name'=> $mainAccount->ma_name,
                    'sob'=> $mainAccount->ma_sob,
                    'remarks'=> $mainAccount->ma_rem,
                    'address'=> $mainAccount->ma_address,
                    'contact_id' => $contact->id,
                ];
                // MainAccount::create($mainAccntArr);
            }
            $page = request('page', 1); // default page = 1
            $perPage = 10; // rows per page
            $accounts = DB::connection('hms')
                ->table('Accounts as a')
                ->leftJoin(
                    'agent_table as ag',
                    'a.ac_agcode',
                    '=',
                    'ag.agent_code'
                )
                ->orderBy(column: 'a.ac_id')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->select([
                    'a.*',
            
                    // select specific agent_table columns
                    'ag.agent_id',
                    'ag.agent_code',
                    'ag.agent_name',
                ])
                ->get();
    
            $accntArr = [];
            $contactArr2 = [];
            
            foreach ($accounts as $account) {
                $contactArr2 = [
                    'created_by'=> $authId,
                    'name'=> $account->ac_conper,
                    'number'=> $account->ac_phone,
                ];
                // $contact2 = Contact::create($contactArr2);

                $accntArr = [
                    'contact_id' => $contact2->id,
                    'updated_by'=> $authId,
                    'agent_id'=> $account->agent_id,
                    'code'=> $account->ac_code,
                    'name'=> $account->ac_name,
                    'sob'=> $account->ac_sob,
                    'remarks'=> $account->ac_note,
                    'address'=> $account->ac_address,
                    'logo'=> $account->ac_logo,
                    'effectivity_date'=> $account->ac_effdate,
                    'renewal_date'=> $account->ac_rendate,
                    'expiry_date'=> $account->ac_expiry,
                    'cancel_date'=> $account->ac_candate,
                    'cancel_reason'=> $account->ac_cancel_reason,
                    'type'=> $account->ac_contype,
                    'payment_type'=> $account->ac_paytype,
                    'contribution_type'=> $account->ac_insurance,
                    'pre_existing_coverage'=> $account->ac_mpec,
                    'billing_cutoff_date'=> $account->ac_billcutoff,
                    'extension_days'=> $account->ac_extension,
                    'additional_extension_days'=> $account->ac_addextension,
                    'reimbursement_no_days'=> $account->ac_reimbursement_no_day,
                    'dental_rate'=> $account->ac_dental,
                    'tin'=> $account->ac_tin,
                    'production_credit'=> $account->ac_prodcred,
                    'vat_classification'=> $account->ac_vatclass,
                    'account_type'=> $account->ac_accttype,
                    'type_of_foreclaims'=> $account->ac_type_foreclaims,
                    'sob'=> $account->ac_sob,
                    'integration'=> $account->ac_integration,
                    'is_vchealth_activated'=> $account->ac_isvchealth_activated,
                    'is_ar_integration'=> $account->ch_ar_integration,
                    'is_showvirtual'=> $account->ac_showvirtual,
                    'commission_type'=> $account->ac_commision_type,
                ];
                // Account::create($accntArr);
                
            }
            // Commit transaction
            // DB::commit();
        } catch (\Exception $e) {
            // Catch and handle any unexpected errors
            DB::rollBack();
        }
        // $billTypes = DB::connection('soa')
        //     ->table('upload')
        //     ->select('up_billtype', 'up_billcode')
        //     ->distinct('up_billtype')
        //     // ->pluck('up_billtype')
        //     ->get()
        //     ->toArray();
        // dd($mainAccounts, 'hits');
        // $users = $this->user->getUsers($request->validated())->toArray();

        // return Inertia::render('users/Index', [
        //     'users' => $users,
        // ]);
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
            $task = $this->user->saveUser($validated);

            // Commit transaction
            // DB::commit();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::ok('User Deleted successfully', Response::HTTP_OK);
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
