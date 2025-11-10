<?php

use App\Http\Controllers\{
    AdminController,
    PermissionController,
    RoleController,
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
    // Only admins can access these
    Route::middleware(['role:admin'])->group(function () {
        Route::resource('admin', AdminController::class);
        Route::prefix('users')->controller(UserController::class)->group(function () {
            Route::get('/', 'index')->name('users.index');
            Route::get('/{id}/edit', 'edit')->name('users.edit');
            Route::post('/update', 'update')->name('users.update');
            Route::get('/create', 'create')->name('users.create');
            Route::post('/store', 'store')->name('users.store');
            Route::post('/destroy', 'destroy')->name('users.destroy');
        });

        //Roles
        Route::prefix('roles')->controller(RoleController::class)->group(function () {
            Route::get('/', 'index')->name('roles.index');
            Route::get('/{id}/edit', 'edit')->name('roles.edit');
            Route::post('/update', 'update')->name('roles.update');
            Route::post('/destroy', 'destroy')->name('roles.destroy');
        });

        //Permissions
        Route::prefix('permissions')->controller(PermissionController::class)->group(function () {
            Route::get('/', 'index')->name('permissions.index');
            Route::get('/{id}/edit', 'edit')->name('permissions.edit');
            Route::post('/update', 'update')->name('permissions.update');
            Route::post('/destroy', 'destroy')->name('permissions.destroy');
        });
    });

    // Only users can access these
    Route::middleware(['role:users'])->group(function () {
    });
});


require __DIR__.'/settings.php';
