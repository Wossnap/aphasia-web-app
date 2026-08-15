<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SpeechAttempt;
use App\Models\User;
use App\Services\Practice\ItemStats;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * A whole category, level by level, from the first to the last.
 *
 * The work-with-him list deliberately shows only what needs a person, which
 * answers "where should I spend an hour" and nothing else. This answers the
 * other question — how is he doing overall, and which rows has he got — by
 * showing every level in practice order with each letter carrying its score.
 *
 * Both read from the same ItemStats, so the two pages cannot drift apart.
 */
class CategoryProgressController extends Controller
{
    public function __construct(private ItemStats $stats)
    {
    }

    public function index(Request $request)
    {
        $categories = Category::orderBy('name')->get();
        $users = User::orderBy('name')->get(['id', 'name']);

        $category = $request->filled('category_id')
            ? $categories->firstWhere('id', (int) $request->query('category_id'))
            : $categories->first();

        $userId = $request->filled('user_id') ? (int) $request->query('user_id') : $this->busiestUser();

        $needsHelpBelow = (float) config('practice.bands.needs_help_below', 0.25);
        $levels = $category ? $this->levels($userId, $category, $needsHelpBelow) : collect();

        return view('admin.word-progress.by-level', [
            'categories' => $categories,
            'users' => $users,
            'category' => $category,
            'userId' => $userId,
            'levels' => $levels,
            'needsHelpBelow' => $needsHelpBelow,
            'summary' => $this->summarise($levels),
        ]);
    }

    /**
     * Every level in the category, in order, whether or not it needs anything.
     *
     * @return Collection<int, array>
     */
    private function levels(?int $userId, Category $category, float $needsHelpBelow): Collection
    {
        return $this->stats->forCategory($userId, $category)
            ->filter(fn ($i) => $i['level'] !== null)
            ->groupBy('level')
            ->map(function ($rows, $level) use ($needsHelpBelow) {
                $letters = $rows->sortBy(fn ($i) => $i['order'] ?? PHP_INT_MAX)->values();
                $known = $letters->filter(fn ($i) => $i['accuracy'] !== null);

                return [
                    'level' => (int) $level,
                    // A single Ge'ez character names its own row better than
                    // "Level 27" ever will; anything longer falls back.
                    'label' => mb_strlen($letters->first()['word']) === 1
                        ? $letters->first()['word']
                        : 'Level ' . $level,
                    'letters' => $letters->all(),
                    'accuracy' => $known->isEmpty() ? null : $known->avg('accuracy'),
                    'attempts' => $letters->sum('attempts'),
                    'stuck' => $known->filter(fn ($i) => $i['accuracy'] < $needsHelpBelow)->count(),
                    'strong' => $known->filter(fn ($i) => $i['accuracy'] >= 0.65)->count(),
                    'untried' => $letters->count() - $known->count(),
                    'size' => $letters->count(),
                ];
            })
            // First level to last, which is the order he works them in and the
            // order he would look for them.
            ->sortBy('level')
            ->values();
    }

    private function summarise(Collection $levels): array
    {
        $known = $levels->filter(fn ($l) => $l['accuracy'] !== null);

        return [
            'levels' => $levels->count(),
            'strong' => $known->filter(fn ($l) => $l['accuracy'] >= 0.65)->count(),
            'middling' => $known->filter(fn ($l) => $l['accuracy'] >= 0.45 && $l['accuracy'] < 0.65)->count(),
            'weak' => $known->filter(fn ($l) => $l['accuracy'] < 0.45)->count(),
            'untried' => $levels->count() - $known->count(),
            'attempts' => $levels->sum('attempts'),
        ];
    }

    private function busiestUser(): ?int
    {
        return SpeechAttempt::query()
            ->whereNotNull('user_id')
            ->selectRaw('user_id, COUNT(*) as n')
            ->groupBy('user_id')
            ->orderByDesc('n')
            ->value('user_id');
    }
}
