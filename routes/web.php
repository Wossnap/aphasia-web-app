<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\AmharicWordController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\App;

// Route::get('/', function () {
//     return view('welcome');
// });

// Route::get('/', [GameController::class, 'index']);
Route::get('/', [AmharicWordController::class, 'practice'])->name('practice.amharic');
// Route::get('/practice/amharic', [AmharicWordController::class, 'practice'])->name('practice.amharic');
Route::get('/api/random-amharic-word', [AmharicWordController::class, 'getRandomWord']);

Route::get('language/{locale}', function ($locale) {
    if (in_array($locale, config('app.available_locales', ['en']))) {
        Session::put('locale', $locale);
        return redirect()->back()->with('reload', true);
    }
    return redirect()->back();
})->name('language.switch');
