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

        $word = $query->inRandomOrder()->first();

        // Return null if no word is found
        if (!$word) {
            return response()->json(null);
        }

        // Return all necessary word data with full asset paths from public folder
        return response()->json([
            'id' => $word->id,
            'word' => $word->word,
            'transliterations' => $word->transliterations,
            'meaning' => $word->meaning,
            'audio_path' => $word->audio_path ? asset('audio/' . $word->audio_path) : null,
            'gif_path' => $word->gif_path ? asset('gifs/' . $word->gif_path) : null,
            'image_path' => $word->image_path ? asset('images/' . $word->image_path) : null,
            'show_in_random' => $word->show_in_random
        ]);
    }

    public function practice()
    {
        $categories = Category::all();
        return view('practice.amharic', compact('categories'));
    }

    public function getCategories()
    {
        $categories = Category::all();
        return response()->json($categories);
    }

    public function getLevels($categoryId)
    {
        $maxLevel = DB::table('category_word')
            ->where('category_id', $categoryId)
            ->max('level');

        return response()->json(range(1, $maxLevel));
    }
}
