<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SpeechAttempt;
use App\Models\User;
use App\Services\Practice\ItemStats;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Work on these with him."
 *
 * Nothing is withheld from him: the items here still come up in practice, and
 * the miss rules are what keep them from becoming walls. But an item he has
 * tried forty times and is getting nowhere with is not going to come good on
 * the forty-first attempt alone — it needs someone beside him for a session
 * or two. This is that list, and it is in the admin rather than in his app on
 * purpose: it is your time, not another screen he has to work out by himself.
 */
class PracticeFocusController extends Controller
{
    public function __construct(private ItemStats $stats)
    {
    }

    public function index(Request $request)
    {
        $categories = Category::orderBy('name')->get();
        $users = User::orderBy('name')->get(['id', 'name']);

        $userId = $request->filled('user_id') ? (int) $request->query('user_id') : $this->busiestUser();

        // How many items in each category are going nowhere, so the page can
        // open on the one that most needs the time rather than on whichever
        // category happens to sort first.
        $needingHelp = $this->countNeedingHelp($userId);

        $category = $request->filled('category_id')
            ? $categories->firstWhere('id', (int) $request->query('category_id'))
            : $categories->sortByDesc(fn ($c) => $needingHelp[$c->id] ?? 0)->first();

        $needsHelpBelow = (float) config('practice.bands.needs_help_below', 0.25);
        $families = $category ? $this->familiesNeedingWork($userId, $category, $needsHelpBelow) : collect();

        return view('admin.practice-focus.index', [
            'categories' => $categories,
            'users' => $users,
            'category' => $category,
            'userId' => $userId,
            'families' => $families,
            'needingHelp' => $needingHelp,
            'needsHelpBelow' => $needsHelpBelow,
        ]);
    }

    /**
     * Items per category that he has tried enough times to judge and is still
     * getting nowhere with.
     *
     * Deliberately one query rather than running the full per-item statistics
     * for every category: this only decides which category the page opens on
     * and what the dropdown says beside each name.
     *
     * @return array<int, int> keyed by category id
     */
    private function countNeedingHelp(?int $userId): array
    {
        $windowDays = (int) config('practice.bands.window_days', 30);
        $minAttempts = (int) config('practice.bands.min_attempts', 4);
        $threshold = (float) config('practice.bands.needs_help_below', 0.25);

        return DB::table('speech_attempts as sa')
            ->join('category_word as cw', 'cw.amharic_word_id', '=', 'sa.amharic_word_id')
            ->where('sa.created_at', '>=', now()->subDays($windowDays))
            ->when($userId, fn ($q) => $q->where('sa.user_id', $userId))
            ->groupBy('cw.category_id', 'sa.amharic_word_id')
            ->havingRaw('COUNT(*) >= ?', [$minAttempts])
            ->havingRaw('SUM(sa.is_correct) / COUNT(*) < ?', [$threshold])
            ->select('cw.category_id')
            ->get()
            ->countBy('category_id')
            ->all();
    }

    /**
     * The families worth sitting down with, whole.
     *
     * A single letter is not the unit of the work: get ገ and the rest of the
     * row follows, so what you need in front of you is the family, its first
     * letter, and whether that first letter is the one going wrong. Listing
     * forty-nine letters separately was the same information arranged so that
     * nobody could act on it.
     *
     * @return Collection<int, array>
     */
    private function familiesNeedingWork(?int $userId, Category $category, float $needsHelpBelow): Collection
    {
        return $this->stats->forCategory($userId, $category)
            ->filter(fn ($i) => $i['level'] !== null)
            ->groupBy('level')
            ->map(function ($rows, $level) use ($needsHelpBelow) {
                // The category's own sequence, so the first letter is the one
                // he would start from.
                $letters = $rows->sortBy(fn ($i) => $i['order'] ?? PHP_INT_MAX)->values();
                $first = $letters->first();
                $known = $letters->filter(fn ($i) => $i['accuracy'] !== null);
                $stuck = $known->filter(fn ($i) => $i['accuracy'] < $needsHelpBelow);

                return [
                    'level' => (int) $level,
                    'first' => $first,
                    // Whether the letter to start from is itself the problem.
                    // If it is, this family is where an hour with him buys the
                    // most, because the rest of the row follows from it.
                    'first_stuck' => $first['accuracy'] !== null && $first['accuracy'] < $needsHelpBelow,
                    'letters' => $letters->all(),
                    'stuck' => $stuck->count(),
                    'attempts' => $letters->sum('attempts'),
                    'accuracy' => $known->isEmpty() ? null : $known->avg('accuracy'),
                    'worst_streak' => $letters->max('miss_streak'),
                    // What came back for the letter you would start on.
                    'heard' => $this->whatCameBack($first['word_id']),
                ];
            })
            ->filter(fn ($f) => $f['stuck'] > 0)
            ->sortBy([
                // Families whose first letter is stuck come first.
                fn ($a, $b) => ($b['first_stuck'] <=> $a['first_stuck']),
                fn ($a, $b) => ($a['accuracy'] ?? 1) <=> ($b['accuracy'] ?? 1),
            ])
            ->values();
    }

    /**
     * What the recogniser actually returned for this item, most common first.
     * The point of the list is to make the next session with him quick to
     * prepare: whether it is his mouth or the machine's ear is usually obvious
     * the moment you see the same wrong word coming back every time.
     *
     * @return array<int, array{text:string, times:int}>
     */
    private function whatCameBack(int $wordId): array
    {
        return SpeechAttempt::query()
            ->where('amharic_word_id', $wordId)
            ->where('is_correct', false)
            ->whereNotNull('transcription')
            ->where('transcription', '<>', '')
            ->selectRaw('transcription, COUNT(*) as times')
            ->groupBy('transcription')
            ->orderByDesc('times')
            ->limit(3)
            ->get()
            ->map(fn ($row) => ['text' => $row->transcription, 'times' => (int) $row->times])
            ->all();
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
