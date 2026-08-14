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

/**
 * "Work on these with him."
 *
 * The engine takes the items he cannot do alone out of solo practice, which
 * stops the walls but does not teach him anything — those items still need a
 * person sitting next to him for the first sessions. This is that list, and
 * it is here rather than in his app on purpose: assisted practice is your
 * time, not another screen he has to work out by himself.
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

        $category = $request->filled('category_id')
            ? $categories->firstWhere('id', (int) $request->query('category_id'))
            : $categories->first();

        $userId = $request->filled('user_id') ? (int) $request->query('user_id') : $this->busiestUser();

        $rows = collect();
        $familyRows = collect();

        if ($category) {
            $items = $this->stats->forCategory($userId, $category);
            $confusions = $this->confusions->graph($userId);

            $quarantineBelow = (float) config('practice.bands.quarantine_below', 0.25);

            $rows = $items
                // Items he has actually tried and cannot yet do alone. Never
                // attempted is not "needs help", it is "not started".
                ->filter(fn ($i) => $i['accuracy'] !== null && $i['accuracy'] < $quarantineBelow)
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
            'quarantineBelow' => (float) config('practice.bands.quarantine_below', 0.25),
        ]);
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
