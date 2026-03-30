<?php

use App\Http\Controllers\{
    AdminController,
    NavigationController,
    NavigationModuleController,
    PermissionController,
    RoleController,
    SoaController,
    UserController,
};
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

// Route::get('/', function () {
//     return Inertia::render('Welcome', [
//         'canRegister' => Features::enabled(false),
//     ]);
// })->name('home');
// Route::get('/', function () {
//     return redirect()->route('login');
// });
Route::get('/', function () {
    return Inertia::render('auth/Login', [
        'canResetPassword' => Features::enabled(Features::resetPasswords()),
        'canRegister' => Features::enabled(false),
        'status' => request()->session()->get('status'),
    ]);
})->name('home');

// for manual testing of import jobs
// Route::prefix('admin')->name('admin.')
//     // ->middleware('check_permissions')
//     ->controller(AdminController::class)->group(function () {
//         Route::get('/start_import', 'startImport')->name('startImport');
//         Route::get('/import_main_accounts', 'importMainAccounts')->name('importMainAccounts');
//         Route::get('/import_accounts', 'importAccounts')->name('importAccounts');
//         Route::get('/import_branches', 'importBranches')->name('importBranches');
// });
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
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
                Route::post('/update_roles', 'updateRoles')->name('update_roles');
                Route::post('/update', 'update')->name('update');
                Route::post('/store', 'store')->name('store');
                Route::post('/destroy', 'destroy')->name('destroy');
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
            Route::get('/file_proxy', 'fileProxy')->name('file_proxy');
            Route::get('/', 'index')->name('index');
            Route::get('/list', 'list')->name('list');
            Route::get('/file_list', 'fileList')->name('file_list');
            Route::post('/preview_file', 'previewFile')->name('preview_file');
            Route::get('/preview_file/{file}', 'previewFile')->name('preview_file_with_param');
            Route::get('/tax_computation', 'taxComputation')->name('tax_computation');
            Route::get('/get_accounts', 'getAccounts')->name('get_accounts');
            Route::get('/get_billing_refs', 'getBillingRefs')->name('get_billing_refs');
            Route::get('/get_branches', 'getBranches')->name('get_branches');
            Route::get('/{id}/show', 'show')->name('show');
            Route::get('/{id}/activities', 'activities')->name('activities');
            Route::get('/create', 'create')->name('create');
            Route::post('/store', 'store')->name('store');
            Route::get('/{id}/edit', 'edit')->name('edit');
            Route::get('/{id}/manage_file', 'manageFile')->name('manage_file');
            Route::get('/{id}/untag', 'untag')->name('untag');
            Route::post('/recompute_tax', 'recomputeTax')->name('recompute_tax');
            Route::post('/update', 'update')->name('update');
            Route::post('/update_tag', 'updateTag')->name('update_tag');
            Route::post('/destroy', 'destroy')->name('destroy');
        });
    });
});


require __DIR__.'/settings.php';
