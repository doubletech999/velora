<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminAuthController;

// Redirect root to admin
Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

// Admin Authentication Routes
Route::prefix('admin')->name('admin.')->group(function () {
    // Login routes (accessible without authentication)
    Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('login.submit');
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
    
    // Protected admin routes (require authentication)
    Route::middleware(['web', 'admin'])->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::get('/sites', [AdminController::class, 'sites'])->name('sites');
        Route::get('/guides', [AdminController::class, 'guides'])->name('guides');
        Route::get('/trips', [AdminController::class, 'trips'])->name('trips');
        Route::get('/reviews', [AdminController::class, 'reviews'])->name('reviews');
        Route::get('/bookings', [AdminController::class, 'bookings'])->name('bookings');
    });
});