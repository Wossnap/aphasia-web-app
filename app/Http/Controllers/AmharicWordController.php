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

        // Return all necessary word data
        return response()->json([
            'id' => $word->id,
            'word' => $word->word,
            'transliterations' => $word->transliterations,
            'meaning' => $word->meaning,
            'audio_path' => $word->audio_path,
            'gif_path' => $word->gif_path,  // This should already have category-specific path
            'image_path' => $word->image_path, // This should already have category-specific path
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
