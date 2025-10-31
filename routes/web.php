<?php

use App\Http\Controllers\{
    UserController,
    AdminController
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
        Route::resource('users', UserController::class);
    });

    // Only users can access these
    Route::middleware(['role:users'])->group(function () {
    });
});


require __DIR__.'/settings.php';
