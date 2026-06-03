<?php

namespace App\Http\Controllers;

use App\Models\AmharicWord;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

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
                    $lastOrder = $lastWord->order;
                    $lastCreated = $lastWord->created_at;

                    $next = (clone $query)
                        ->where(function($q) use ($lastOrder, $lastCreated) {
                            if ($lastOrder !== null) {
                                // Next word with higher order, OR same order but later created_at, OR no order (nulls come after ordered)
                                $q->where('order', '>', $lastOrder)
                                  ->orWhere(function($q2) use ($lastOrder, $lastCreated) {
                                      $q2->where('order', $lastOrder)->where('created_at', '>', $lastCreated);
                                  })
                                  ->orWhereNull('order');
                            } else {
                                $q->where('created_at', '>', $lastCreated)->whereNull('order');
                            }
                        })
                        ->orderByRaw('CASE WHEN `order` IS NULL THEN 1 ELSE 0 END')
                        ->orderBy('order', 'asc')
                        ->orderBy('created_at', 'asc')
                        ->first();

                    if (!$next) {
                        $next = (clone $query)
                            ->orderByRaw('CASE WHEN `order` IS NULL THEN 1 ELSE 0 END')
                            ->orderBy('order', 'asc')
                            ->orderBy('created_at', 'asc')
                            ->first();
                    }

                    $word = $next;
                } else {
                    $word = $query
                        ->orderByRaw('CASE WHEN `order` IS NULL THEN 1 ELSE 0 END')
                        ->orderBy('order', 'asc')
                        ->orderBy('created_at', 'asc')
                        ->first();
                }
            } else {
                $word = $query
                    ->orderByRaw('CASE WHEN `order` IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('order', 'asc')
                    ->orderBy('created_at', 'asc')
                    ->first();
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
            'gif_path'   => $word->gif_path,
            'image_path' => $word->image_path,
            'show_in_random' => $word->show_in_random,
            'engine' => $word->engine
        ]);
    }

    public function practice($categorySlug = null, $level = null)
    {
        $categories = Category::all()->map(fn($c) => [
            'id'   => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
        ]);

        // Resolve the initial deep-link target (if any) so the page can open
        // straight onto that category's levels / a specific practice level.
        $initialCategory = $categorySlug
            ? $categories->firstWhere('slug', $categorySlug)
            : null;

        return Inertia::render('Practice', [
            'categories'   => $categories,
            'speechDriver' => config('services.google_speech.driver', 'browser'),
            'speechVersion' => config('services.google_speech.version', 'v1'),
            'initialSlug'  => $initialCategory['slug'] ?? null,
            'initialCategoryId' => $initialCategory['id'] ?? null,
            'initialLevel' => $initialCategory && $level !== null ? (int) $level : null,
            'translations' => [
                'next_word' => __('app.next_word'),
                'excellent' => __('app.excellent'),
                'you_said'  => __('app.you_said'),
                'try_again' => __('app.try_again'),
            ],
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
            // Pull every word in the category with its level + order in one query.
            $rows = DB::table('category_word')
                ->join('amharic_words', 'amharic_words.id', '=', 'category_word.amharic_word_id')
                ->where('category_word.category_id', $categoryId)
                ->select('category_word.level', 'amharic_words.word', 'amharic_words.order')
                ->get();

            if ($rows->isEmpty()) {
                return response()->json([['level' => 1, 'label' => null]]);
            }

            // For each level, the representative word = lowest order. If it is a
            // single character (e.g. a fidel base like ሀ), use it as the label;
            // otherwise leave null so the UI falls back to "Level N".
            $levels = $rows->groupBy('level')
                ->map(function ($group, $level) {
                    $first = $group->sortBy(fn ($r) => $r->order ?? PHP_INT_MAX)->first();
                    $label = ($first && mb_strlen($first->word) === 1) ? $first->word : null;
                    return ['level' => (int) $level, 'label' => $label];
                })
                ->sortBy('level')
                ->values();

            return response()->json($levels);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
