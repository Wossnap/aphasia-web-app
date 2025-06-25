<?php

namespace App\Http\Controllers;

use App\Models\AmharicWord;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    public function dashboard()
    {
        // Debug logging
        Log::info('Dashboard accessed', [
            'user_id' => auth()->id(),
            'user_email' => auth()->user()->email ?? 'unknown'
        ]);

        $stats = [
            'total_words' => AmharicWord::count(),
            'total_categories' => Category::count(),
            'words_with_audio' => AmharicWord::whereNotNull('audio_path')->count(),
            'words_with_images' => AmharicWord::whereNotNull('image_path')->count(),
            'words_with_gifs' => AmharicWord::whereNotNull('gif_path')->count(),
            'random_words' => AmharicWord::where('show_in_random', true)->count(),
        ];

        $recent_words = AmharicWord::latest()->take(5)->get();
        $categories = Category::withCount('words')->get();

        return view('admin.dashboard', compact('stats', 'recent_words', 'categories'));
    }
}
