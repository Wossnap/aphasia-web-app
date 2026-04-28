<?php

namespace App\Http\Controllers;

use App\Models\AmharicWord;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AmharicWordController extends Controller
{
    public function getRandomWord(Request $request)
    {
        $query = AmharicWord::query();

        if ($request->has('category_id')) {
            $query->whereHas('categories', function($q) use ($request) {
                $q->where('categories.id', $request->category_id);
                if ($request->has('level')) {
                    $q->where('level', $request->level);
                }
            });
        } else {
            $query->where('show_in_random', true);
        }

        $mode = $request->input('mode', 'random');

        if ($mode === 'consecutive') {
            $lastId = $request->input('last_id');

            if ($lastId) {
                $lastWord = AmharicWord::find($lastId);
                if ($lastWord) {
                    $next = (clone $query)
                        ->where('created_at', '>', $lastWord->created_at)
                        ->orderBy('created_at', 'asc')
                        ->first();

                    // Wrap around to the beginning when we reach the end
                    if (!$next) {
                        $next = (clone $query)->orderBy('created_at', 'asc')->first();
                    }

                    $word = $next;
                } else {
                    $word = $query->orderBy('created_at', 'asc')->first();
                }
            } else {
                $word = $query->orderBy('created_at', 'asc')->first();
            }
        } else {
            $word = $query->inRandomOrder()->first();
        }

        if (!$word) {
            return response()->json(null);
        }

        return response()->json([
            'id' => $word->id,
            'word' => $word->word,
            'transliterations' => $word->transliterations,
            'meaning' => $word->meaning,
            'audio_path' => $word->audio_path,
            'gif_path' => $word->gif_path,
            'image_path' => $word->image_path,
            'show_in_random' => $word->show_in_random
        ]);
    }

    public function practice()
    {
        $categories = Category::all();
        return view('practice.amharic', compact('categories'))->with([
            'speechDriver' => config('services.google_speech.driver', 'browser')
        ]);
    }

    public function getCategories()
    {
        $categories = Category::all();
        return response()->json($categories);
    }

    public function getLevels($categoryId)
    {
        try {
            $maxLevel = DB::table('category_word')
                ->where('category_id', $categoryId)
                ->max('level');

            // Ensure maxLevel is at least 1 and cast to int to avoid PHP 8.1+ deprecation warnings in range()
            $maxLevel = max(1, (int)$maxLevel);

            return response()->json(range(1, $maxLevel));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
