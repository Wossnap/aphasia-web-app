<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\SpeechAttempt;
use App\Services\Practice\SessionPlanner;
use Illuminate\Http\Request;

/**
 * The engine-driven practice endpoint: the app asks what is next, and the
 * server answers from his history rather than the browser deciding.
 *
 * Deliberately separate from the old /api/random-amharic-word, which still
 * serves the free-practice modes. Guided sessions are a different thing and
 * should not have to fight the old query for control of what comes next.
 */
class PracticeSessionController extends Controller
{
    public function __construct(private SessionPlanner $planner)
    {
    }

    public function next(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|integer|exists:categories,id',
        ]);

        $category = Category::findOrFail($validated['category_id']);
        $plan = $this->planner->next(auth()->id(), $category);

        if ($plan['item'] && auth()->check()) {
            $plan['item']['progress'] = SpeechAttempt::progressFor(auth()->id(), $plan['item']['id']);
        }

        return response()->json($plan);
    }
}
