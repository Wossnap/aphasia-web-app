<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\AmharicWordController;
use App\Http\Controllers\SpeechController;
use App\Http\Controllers\UserAuthController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\App;

// Guest authentication routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [UserAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [UserAuthController::class, 'login'])->name('login.post');
    Route::get('/register', [UserAuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [UserAuthController::class, 'register'])->name('register.post');
});

// Protected Practice App routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [UserAuthController::class, 'logout'])->name('logout');

    Route::get('/', [AmharicWordController::class, 'practice'])->name('practice.amharic');
    Route::get('/api/random-amharic-word', [AmharicWordController::class, 'getRandomWord']);
    Route::get('/api/categories', [AmharicWordController::class, 'getCategories']);
    Route::get('/api/categories/{category}/levels', [AmharicWordController::class, 'getLevels']);
    Route::post('/api/transcribe', [SpeechController::class, 'transcribe'])->name('api.transcribe');

    // Deep-linkable practice URLs: /{slug} (levels) and /{slug}/level-{n} (practice).
    // Registered last; the constraint excludes reserved prefixes so it never shadows
    // api/admin/asset routes. The page renders for any slug; an unknown one just
    // falls back to the category list client-side.
    $reservedSlugs = 'api|admin|language|build|audio|images|gifs|storage|css|js|fonts';
    Route::get('/{categorySlug}', [AmharicWordController::class, 'practice'])
        ->where('categorySlug', '(?!(' . $reservedSlugs . ')$)[a-z0-9-]+')
        ->name('practice.category');
    Route::get('/{categorySlug}/level-{level}', [AmharicWordController::class, 'practice'])
        ->where(['categorySlug' => '(?!(' . $reservedSlugs . ')$)[a-z0-9-]+', 'level' => '[0-9]+'])
        ->name('practice.level');
});

Route::get('language/{locale}', function ($locale) {
    if (in_array($locale, config('app.available_locales', ['en']))) {
        Session::put('locale', $locale);
        return redirect()->back()->with('reload', true);
    }
    return redirect()->back();
})->name('language.switch');

