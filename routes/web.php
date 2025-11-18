<?php

use App\Http\Controllers\{
    AdminController,
    PermissionController,
    RoleController,
    SoaController,
    UserController,
};
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

// Route::resource('admin', AdminController::class);
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');
// Route::get('/', function () {
//     return redirect('login');
// });

// Route::get('dashboard', function () {
//     return Inertia::render('Dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
    // Superadmin-only routes - only admins can access these
    Route::middleware(['role:superadmin'])->group(function () {
        Route::prefix('admin')->name('admin.')->controller(AdminController::class)->group(function () {
            Route::get('/start_import', 'startImport')->name('startImport');
            Route::get('/import_main_accounts', 'importMainAccounts')->name('importMainAccounts');
            Route::get('/import_accounts', 'importAccounts')->name('importAccounts');
        });
        Route::resource('admin', AdminController::class);
        Route::prefix('users')->name('users.')->controller(UserController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{id}/edit', 'edit')->name('edit');
            Route::post('/update', 'update')->name('update');
            Route::get('/create', 'create')->name('create');
            Route::post('/store', 'store')->name('store');
            Route::post('/destroy', 'destroy')->name('destroy');
        });

        //Roles
        Route::prefix('roles')->name('roles.')->controller(RoleController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{id}/edit', 'edit')->name('edit');
            Route::post('/update', 'update')->name('update');
            Route::post('/store', 'store')->name('store');
            Route::post('/destroy', 'destroy')->name('destroy');
        });

        //Permissions
        Route::prefix('permissions')->name('permissions.')->controller(PermissionController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{id}/edit', 'edit')->name('edit');
            Route::post('/update', 'update')->name('update');
            Route::post('/store', 'store')->name('store');
            Route::post('/destroy', 'destroy')->name('destroy');
        });
    });

    // User routes - admins can access these too, but regular users can only access their own routes
    // Using allow_admin_or_role middleware: admin can access everything, users can only access their specific routes
    Route::middleware(['allow_admin_or_role:user'])->group(function () {
        //user_dashboard
        Route::prefix('soa')->name('soa.')->controller(SoaController::class)->group(function () {
            Route::get('/', 'index')->name('index');
        });
    });
});


require __DIR__.'/settings.php';
