<?php

namespace App\Http\Controllers;

use App\Models\AmharicWord;
use App\Models\Category;
use Illuminate\Http\Request;

class AmharicWordController extends Controller
{
    public function getRandomWord(Request $request)
    {
        $query = AmharicWord::query();

        if ($request->category_id) {
            $query->whereHas('categories', function($q) use ($request) {
                $q->where('categories.id', $request->category_id);
                if ($request->level) {
                    $q->where('category_word.level', $request->level);
                }
            });
        }

        $word = $query->inRandomOrder()->first();

        return response()->json([
            'word' => $word->word,
            'transliterations' => $word->transliterations,
            'meaning' => $word->meaning,
            'audio_path' => $word->audio_path
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
        $maxLevel = \DB::table('category_word')
            ->where('category_id', $categoryId)
            ->max('level');

        return response()->json(range(1, $maxLevel));
    }
}
