<?php

use App\Enums\SoaAging;
use App\Http\Controllers\{
    AccountPaymentController,
    AdminController,
    ConcernController,
    NavigationController,
    NavigationModuleController,
    PermissionController,
    RoleController,
    SoaController,
    UserController,
};
use App\Models\{ AccountPayment, Concern, Soa };
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

// Route::get('/', function () {
//     return Inertia::render('Welcome', [
//         'canRegister' => Features::enabled(false),
//     ]);
// })->name('home');
// Route::get('/test-email/{id}', function ($id) {
//     $agingValue = SoaAging::PAST_DUE;

//     return view('emails.esoa.billing-invoice-due-reminder', [
//         'agingLabel' => SoaAging::label($agingValue),
//         'soaCount' => 5,
//         'listUrl' => SoaAging::listUrl($agingValue),
//     ]);
//     // return view('emails.esoa.billing-invoice-status-changed', [
//     //     'soa' => Soa::findOrFail($id),
//     // ]);
//     // return view('emails.esoa.concern-notification', [
//     //     'concern' => Concern::findOrFail($id),
//     // ]);
//     // return view('emails.esoa.account-payment-notification', [
//     //     'accountPayment' => AccountPayment::findOrFail($id),
//     // ]);
// });
Route::get('/', function () {
    return Inertia::render('auth/Login', [
        'canResetPassword' => Features::enabled(Features::resetPasswords()),
        'canRegister' => false,
        'status' => request()->session()->get('status'),
    ]);
})->name('home');

// for manual testing of import jobs
Route::prefix('admin')->name('admin.')
    // ->middleware('check_permissions')
    ->controller(AdminController::class)->group(function () {
        // Route::get('/import_soa', 'importSoa')->name('importSoa');
        // Route::get('/start_import', 'startImport')->name('startImport');
        // Route::get('/import_main_accounts', 'importMainAccounts')->name('importMainAccounts');
        // Route::get('/import_accounts', 'importAccounts')->name('importAccounts');
        // Route::get('/import_branches', 'importBranches')->name('importBranches');
});
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('Home');
    })->name('dashboard');
    // Superadmin-only routes - only admins can access these
    Route::middleware(['role:superadmin'])->group(function () {
        Route::resource('admin', AdminController::class)->middleware('check_permissions');
        Route::prefix('admin')->name('admin.')
            ->middleware('check_permissions')
            ->controller(AdminController::class)->group(function () {
                Route::get('/start_import', 'startImport')->name('startImport');
                Route::get('/import_main_accounts', 'importMainAccounts')->name('importMainAccounts');
                Route::get('/import_accounts', 'importAccounts')->name('importAccounts');
                Route::get('/import_branches', 'importBranches')->name('importBranches');
        });
        Route::prefix('users')->name('users.')
            ->middleware('check_permissions')
            ->controller(UserController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{id}/edit', 'edit')->name('edit');
                Route::get('/create', 'create')->name('create');
                Route::get('/access', 'access')->name('access');
                Route::get('/get_accounts', 'getAccounts')->name('get_accounts');
                Route::get('/get_branches', 'getBranches')->name('get_branches');
                Route::post('/update_access', 'updateAccess')->name('update_access');
                Route::get('/{id}/edit_roles', 'editRoles')->name('edit_roles');
                Route::get('/all_roles', 'allRoles')->name('all_roles');
                Route::post('/update_roles', 'updateRoles')->name('update_roles');
                Route::post('/bulk_update_roles', 'bulkUpdateRoles')->name('bulk_update_roles');
                Route::post('/update', 'update')->name('update');
                Route::post('/store', 'store')->name('store');
                Route::post('/destroy', 'destroy')->name('destroy');
                Route::post('/verify', 'verify')->name('verify');
                Route::post('/toggle_active', 'toggleActive')->name('toggle_active');
                Route::post('/bulk_toggle_active', 'bulkToggleActive')->name('bulk_toggle_active');
                Route::post('/bulk_destroy', 'bulkDestroy')->name('bulk_destroy');
        });

        //Roles
        Route::prefix('roles')->name('roles.')
            ->middleware('check_permissions')
            ->controller(RoleController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{id}/edit', 'edit')->name('edit');
                Route::post('/update', 'update')->name('update');
                Route::get('/create', 'create')->name('create');
                Route::post('/store', 'store')->name('store');
                Route::post('/destroy', 'destroy')->name('destroy');
                Route::get('/{id}/edit_permissions', 'editPermissions')->name('edit_permissions');
                Route::post('/update_permissions', 'updatePermissions')->name('update_permissions');
        });

        //Permissions
        Route::prefix('permissions')->name('permissions.')
            ->middleware('check_permissions')
            ->controller(PermissionController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{id}/edit', 'edit')->name('edit');
                Route::get('/create', 'create')->name('create');
                Route::post('/update', 'update')->name('update');
                Route::post('/store', 'store')->name('store');
                Route::post('/destroy', 'destroy')->name('destroy');
        });

        //Navigations
        Route::prefix('navigations')->name('navigations.')
            ->middleware('check_permissions')
            ->controller(NavigationController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{id}/edit', 'edit')->name('edit');
                Route::get('/create', 'create')->name('create');
                Route::post('/update', 'update')->name('update');
                Route::post('/store', 'store')->name('store');
                Route::post('/destroy', 'destroy')->name('destroy');
        });

        //Navigation modules
        Route::prefix('navigation_modules')->name('navigation_modules.')
            ->middleware('check_permissions')
            ->controller(NavigationModuleController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{id}/edit', 'edit')->name('edit');
                Route::get('/create', 'create')->name('create');
                Route::post('/update', 'update')->name('update');
                Route::post('/store', 'store')->name('store');
                Route::post('/destroy', 'destroy')->name('destroy');
        });
    });

    // User routes - admins can access these too, but regular users can only access their own routes
    // Using allow_admin_or_role middleware: admin can access everything, users can only access their specific routes
    Route::middleware(['check_permissions'])->group(function () {
        //user_dashboard
        Route::prefix('soas')->name('soas.')->controller(SoaController::class)->group(function () {
            Route::get('/dashboard', 'dashboard')->name('dashboard');
            Route::get('/file_proxy', 'fileProxy')->name('file_proxy');
            Route::get('/', 'index')->name('index');
            Route::get('/list', 'list')->name('list');
            Route::get('/export', 'exportList')->name('export');
            Route::get('/file_list', 'fileList')->name('file_list');
            Route::get('/preview_file', 'previewFile')->name('preview_file');
            Route::get('/find_member', 'findMember')->name('find_member');
            Route::get('/member_files', 'memberFiles')->name('member_files');
            Route::get('/get_accounts', 'getAccounts')->name('get_accounts');
            Route::get('/get_billing_refs', 'getBillingRefs')->name('get_billing_refs');
            Route::get('/get_branches', 'getBranches')->name('get_branches');
            Route::get('/{id}/show', 'show')->name('show');
            Route::get('/{id}/activities', 'activities')->name('activities');
            Route::get('/{id}/concerns', 'concerns')->name('concerns');
            Route::get('/{id}/account_payments', 'soaAccountPayments')->name('account_payments');
            Route::post('/{id}/record_viewed', 'recordViewed')->name('record_viewed');
            Route::get('/{id}/view_billing_invoice', 'viewBillingInvoice')->name('view_billing_invoice');
            Route::get('/create', 'create')->name('create');
            Route::post('/store', 'store')->name('store');
            Route::get('/{id}/edit', 'edit')->name('edit');
            Route::get('/{id}/attachment/{type}', 'streamBillingAttachment')
                ->name('billing_attachments')
                ->where('type', 'pdf|excel');
            Route::get('/{account_code}/{branch_code}/members', 'accountBranchMembers')->name('account_branch_members');
            Route::get('/{id}/manage_file', 'manageFile')->name('manage_file');
            Route::post('/adjust_amount', 'adjustAmount')->name('adjust_amount');
            Route::post('/update', 'update')->name('update');
            Route::post('/destroy', 'destroy')->name('destroy');
        });

        Route::prefix('concerns')->name('concerns.')->controller(ConcernController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/preview_file', 'previewFile')->name('preview_file');
            Route::get('/{id}/show', 'show')->name('show');
            Route::get('/create', 'create')->name('create');
            Route::post('/store', 'store')->name('store');
            Route::get('/{id}/edit', 'edit')->name('edit');
            Route::get('/{id}/untag', 'untag')->name('untag');
            Route::post('/update', 'update')->name('update');
            Route::post('/destroy', 'destroy')->name('destroy');
        });

        Route::prefix('account_payments')->name('account_payments.')->controller(AccountPaymentController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/preview_file', 'previewFile')->name('preview_file');
            Route::get('/{id}/show', 'show')->name('show');
            Route::get('/create', 'create')->name('create');
            Route::post('/store', 'store')->name('store');
            Route::get('/{id}/edit', 'edit')->name('edit');
            Route::post('/update', 'update')->name('update');
            Route::post('/destroy', 'destroy')->name('destroy');
        });
    });
});


require __DIR__.'/settings.php';
