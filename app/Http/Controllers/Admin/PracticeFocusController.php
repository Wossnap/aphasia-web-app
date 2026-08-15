<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SpeechAttempt;
use App\Models\User;
use App\Services\Practice\ConfusionGraph;
use App\Services\Practice\ItemStats;
use App\Services\Practice\WordFamily;
use Illuminate\Http\Request;
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
    public function __construct(
        private ItemStats $stats,
        private WordFamily $families,
        private ConfusionGraph $confusions,
    ) {
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

        $rows = collect();
        $familyRows = collect();

        if ($category) {
            $items = $this->stats->forCategory($userId, $category);
            $confusions = $this->confusions->graph($userId);

            $needsHelpBelow = (float) config('practice.bands.needs_help_below', 0.25);

            $rows = $items
                // Items he has actually tried and is getting nowhere with on
                // his own. Never attempted is not "needs help", it is "not
                // started". These are not withheld from him — he still meets
                // them in practice — they are simply the ones worth your time.
                ->filter(fn ($i) => $i['accuracy'] !== null && $i['accuracy'] < $needsHelpBelow)
                ->map(function ($item) use ($confusions, $items) {
                    $heard = $this->whatCameBack($item['word_id']);

                    return $item + [
                        'family' => $this->families->of($item['word']),
                        'heard' => $heard,
                        'confused_with' => collect(array_keys($confusions[$item['word_id']] ?? []))
                            ->map(fn ($id) => $items[$id]['word'] ?? null)
                            ->filter()
                            ->values()
                            ->all(),
                    ];
                })
                ->sortBy('accuracy')
                ->values();

            // Grouped by consonant family, because the family is the unit that
            // is actually stuck: pulling one letter out and leaving its six
            // siblings in rotation just spreads the same wall over seven items.
            $familyRows = $rows
                ->filter(fn ($r) => $r['family'] !== null)
                ->groupBy('family')
                ->map(fn ($group) => [
                    'letters' => $group->pluck('word')->all(),
                    'count' => $group->count(),
                    'accuracy' => round($group->avg('accuracy') * 100),
                ])
                ->sortByDesc('count')
                ->values();
        }

        return view('admin.practice-focus.index', [
            'categories' => $categories,
            'users' => $users,
            'category' => $category,
            'userId' => $userId,
            'rows' => $rows,
            'familyRows' => $familyRows,
            'needingHelp' => $needingHelp,
            'needsHelpBelow' => (float) config('practice.bands.needs_help_below', 0.25),
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
