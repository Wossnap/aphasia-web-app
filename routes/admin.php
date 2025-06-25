<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Admin\WordController;
use App\Http\Controllers\Admin\CategoryController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

// Test route for debugging
Route::get('/test', function() {
    return response()->json([
        'authenticated' => Auth::check(),
        'user' => Auth::user(),
        'session_id' => session()->getId()
    ]);
})->name('admin.test');

// Quick test login
Route::get('/quick-login', function() {
    $admin = \App\Models\User::where('email', 'admin@aphasia.com')->first();
    if ($admin) {
        Auth::login($admin);
        return redirect()->route('admin.dashboard');
    }
    return 'Admin user not found';
})->name('admin.quick-login');

// Admin Authentication Routes (with web middleware for sessions)
Route::middleware('web')->group(function() {
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('admin.login.post');
});

// Protected Admin Routes
Route::middleware(['web', 'admin'])->group(function () {
    // Admin Dashboard
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

    // Words Management
    Route::resource('words', WordController::class)->names([
        'index' => 'admin.words.index',
        'create' => 'admin.words.create',
        'store' => 'admin.words.store',
        'show' => 'admin.words.show',
        'edit' => 'admin.words.edit',
        'update' => 'admin.words.update',
        'destroy' => 'admin.words.destroy',
    ]);

    // Categories Management
    Route::resource('categories', CategoryController::class)->names([
        'index' => 'admin.categories.index',
        'create' => 'admin.categories.create',
        'store' => 'admin.categories.store',
        'show' => 'admin.categories.show',
        'edit' => 'admin.categories.edit',
        'update' => 'admin.categories.update',
        'destroy' => 'admin.categories.destroy',
    ]);

    // Category-Word Management
    Route::get('categories/{category}/manage-words', [CategoryController::class, 'manageWords'])->name('admin.categories.manage-words');
    Route::post('categories/{category}/add-word', [CategoryController::class, 'addWord'])->name('admin.categories.add-word');
    Route::delete('categories/{category}/words/{word}', [CategoryController::class, 'removeWord'])->name('admin.categories.remove-word');
    Route::patch('categories/{category}/words/{word}/level', [CategoryController::class, 'updateWordLevel'])->name('admin.categories.update-word-level');
});
