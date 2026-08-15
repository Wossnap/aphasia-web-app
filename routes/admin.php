<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Admin\WordController;
use Wossnap\AmharicTransliteration\Facades\AmharicTransliteration;
use App\Http\Controllers\Admin\CategoryController;
use Illuminate\Support\Facades\Route;

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

    // Transliteration API
    Route::get('words/autofill-transliterations', function (Illuminate\Http\Request $request) {
        $word = $request->input('word', '');
        if (!$word) {
            return response()->json(['pills' => []]);
        }
        $pills = array_values(array_unique(array_merge(
            [AmharicTransliteration::transliterate($word)],
            AmharicTransliteration::getAmharicVariants($word)
        )));
        return response()->json(['pills' => $pills]);
    })->name('admin.words.autofill-transliterations');

    // Bulk-set speech engine on selected words
    Route::post('words/bulk-engine', [WordController::class, 'bulkEngine'])->name('admin.words.bulk-engine');

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

    // Speech Attempts Log Management
    Route::get('attempts', [\App\Http\Controllers\Admin\SpeechAttemptController::class, 'index'])->name('admin.attempts.index');
    Route::post('attempts/{attempt}/add-transliteration', [\App\Http\Controllers\Admin\SpeechAttemptController::class, 'addTransliteration'])->name('admin.attempts.add-transliteration');
    // Registered before the {attempt} route so "bulk-delete" isn't read as an id.
    Route::delete('attempts/bulk-delete', [\App\Http\Controllers\Admin\SpeechAttemptController::class, 'bulkDestroy'])->name('admin.attempts.bulk-destroy');
    Route::delete('attempts/{attempt}', [\App\Http\Controllers\Admin\SpeechAttemptController::class, 'destroy'])->name('admin.attempts.destroy');

    // Attempt volume analytics (per user, over time)
    Route::get('analytics', [\App\Http\Controllers\Admin\AttemptAnalyticsController::class, 'index'])->name('admin.analytics.index');

    // Assisted practice: the items the engine has taken out of solo practice
    // and which need someone sitting with him.
    Route::get('practice-focus', [\App\Http\Controllers\Admin\PracticeFocusController::class, 'index'])->name('admin.practice-focus.index');

    // A whole category level by level, first to last, with a score per letter.
    Route::get('progress-by-level', [\App\Http\Controllers\Admin\CategoryProgressController::class, 'index'])->name('admin.progress-by-level.index');

    // Per-word progress tracking
    Route::get('word-progress', [\App\Http\Controllers\Admin\WordProgressController::class, 'index'])->name('admin.word-progress.index');
    Route::get('word-progress/categories', [\App\Http\Controllers\Admin\WordProgressController::class, 'categories'])->name('admin.word-progress.categories');
    Route::get('word-progress/levels', [\App\Http\Controllers\Admin\WordProgressController::class, 'levels'])->name('admin.word-progress.levels');
});
