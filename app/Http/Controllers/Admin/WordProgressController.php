<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AmharicWord;
use App\Models\SpeechAttempt;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WordProgressController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->query('user_id');
        $wordId = $request->query('word_id');
        $rows = $this->progressRows($userId, $wordId);

        // Dashboard summary over the currently filtered rows (before the status
        // filter / pagination below), so the counts and focus/mastered picks
        // always match whatever User/Word filter is active.
        $stats = [
            'mastered' => $rows->where('status', 'mastered')->count(),
            'improving' => $rows->where('status', 'improving')->count(),
            'needs_practice' => $rows->where('status', 'needs_practice')->count(),
        ];
        $focusWords = $rows->where('status', 'needs_practice')->sortBy('score')->take(5)->values();
        $masteredWords = $rows->where('status', 'mastered')->sortByDesc('score')->take(5)->values();

        $categorySummaries = $this->summarizeCategories($rows);
        $focusCategories = $categorySummaries->sortBy('avg_score')->take(3)->values();
        $greatCategories = $categorySummaries->sortByDesc('avg_score')->take(3)->values();

        $levelSummaries = $this->summarizeLevels($rows);
        $focusLevels = $levelSummaries->sortBy('avg_score')->take(5)->values();
        $greatLevels = $levelSummaries->sortByDesc('avg_score')->take(5)->values();

        // Full word table: status filter + score sort, independent of the
        // dashboard summary above.
        $status = $request->query('status');
        $sort = $request->query('sort') === 'desc' ? 'desc' : 'asc';

        $tableRows = $rows->when($status, fn ($q) => $q->where('status', $status));
        $tableRows = $sort === 'desc' ? $tableRows->sortByDesc('score') : $tableRows->sortBy('score');
        $tableRows = $tableRows->values();

        $perPage = 50;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $items = $tableRows->slice(($page - 1) * $perPage, $perPage)->values();

        $progress = new LengthAwarePaginator(
            $items,
            $tableRows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        // Only words that have at least one attempt are worth offering as a
        // filter — anything else would always return an empty table.
        $words = AmharicWord::whereIn('id', SpeechAttempt::whereNotNull('amharic_word_id')->distinct()->pluck('amharic_word_id'))
            ->orderBy('word')
            ->get(['id', 'word']);

        return view('admin.word-progress.index', compact(
            'progress', 'users', 'userId', 'words', 'wordId', 'status', 'sort', 'stats',
            'focusWords', 'masteredWords', 'focusCategories', 'greatCategories', 'focusLevels', 'greatLevels'
        ));
    }

    public function categories(Request $request)
    {
        $userId = $request->query('user_id');
        $wordId = $request->query('word_id');
        $rows = $this->progressRows($userId, $wordId);

        $status = $request->query('status');
        $sort = $request->query('sort') === 'desc' ? 'desc' : 'asc';

        $list = $this->summarizeCategories($rows)->when($status, fn ($q) => $q->where('status', $status));
        $list = $sort === 'desc' ? $list->sortByDesc('avg_score') : $list->sortBy('avg_score');
        $list = $list->values();

        return view('admin.word-progress.categories', compact('list', 'status', 'sort', 'userId', 'wordId'));
    }

    public function levels(Request $request)
    {
        $userId = $request->query('user_id');
        $wordId = $request->query('word_id');
        $rows = $this->progressRows($userId, $wordId);

        $status = $request->query('status');
        $sort = $request->query('sort') === 'desc' ? 'desc' : 'asc';

        $list = $this->summarizeLevels($rows)->when($status, fn ($q) => $q->where('status', $status));
        $list = $sort === 'desc' ? $list->sortByDesc('avg_score') : $list->sortBy('avg_score');
        $list = $list->values();

        return view('admin.word-progress.levels', compact('list', 'status', 'sort', 'userId', 'wordId'));
    }

    /**
     * Every (user, word) pair with at least one attempt, with progress computed
     * and the user/word models attached. Shared base for the dashboard and the
     * dedicated category/level full-list pages so they stay in sync.
     */
    private function progressRows(?string $userId, ?string $wordId): Collection
    {
        $pairs = SpeechAttempt::query()
            ->whereNotNull('user_id')
            ->whereNotNull('amharic_word_id')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($wordId, fn ($q) => $q->where('amharic_word_id', $wordId))
            ->select('user_id', 'amharic_word_id')
            ->distinct()
            ->get();

        $usersById = User::whereIn('id', $pairs->pluck('user_id')->unique())->get()->keyBy('id');
        $wordsById = AmharicWord::with('categories')
            ->whereIn('id', $pairs->pluck('amharic_word_id')->unique())
            ->get()
            ->keyBy('id');

        return $pairs
            ->map(function ($pair) use ($usersById, $wordsById) {
                $progress = SpeechAttempt::progressFor($pair->user_id, $pair->amharic_word_id);
                if (!$progress) {
                    return null;
                }
                $progress['user'] = $usersById->get($pair->user_id);
                $progress['word'] = $wordsById->get($pair->amharic_word_id);
                return $progress;
            })
            ->filter()
            ->values();
    }

    /**
     * Roll each word's score up into every category it belongs to, so a
     * caregiver can see "which category needs work" instead of scanning
     * individual words. A word in multiple categories counts toward each.
     */
    private function summarizeCategories(Collection $rows): Collection
    {
        $categoryStats = [];
        foreach ($rows as $row) {
            foreach ($row['word']?->categories ?? [] as $category) {
                $categoryStats[$category->id] ??= ['category' => $category, 'scores' => []];
                $categoryStats[$category->id]['scores'][] = $row['score'];
            }
        }

        $improvingScore = (int) config('services.word_progress.improving_score', 5);

        return collect($categoryStats)
            ->map(function ($entry) use ($improvingScore) {
                $avg = array_sum($entry['scores']) / count($entry['scores']);
                return [
                    'category' => $entry['category'],
                    'word_count' => count($entry['scores']),
                    'avg_score' => round($avg, 1),
                    'status' => $avg >= 8 ? 'doing_great' : ($avg >= $improvingScore ? 'improving' : 'needs_focus'),
                ];
            })
            ->values();
    }

    /**
     * Roll up one level finer: within a category, words are grouped into
     * levels (e.g. the ሀ-family vs the ለ-family inside "Fidel"). Label each
     * level the same way the practice UI does — the lowest-order word in
     * that level when it's a single character, else "Level N".
     */
    private function summarizeLevels(Collection $rows): Collection
    {
        $categoryIds = $rows->pluck('word')->filter()
            ->flatMap(fn ($w) => $w->categories->pluck('id'))
            ->unique()
            ->values();

        $levelLabels = [];
        if ($categoryIds->isNotEmpty()) {
            $levelRows = DB::table('category_word')
                ->join('amharic_words', 'amharic_words.id', '=', 'category_word.amharic_word_id')
                ->whereIn('category_word.category_id', $categoryIds)
                ->select('category_word.category_id', 'category_word.level', 'amharic_words.word', 'amharic_words.order')
                ->get()
                ->groupBy(fn ($r) => $r->category_id . '-' . $r->level);

            foreach ($levelRows as $key => $group) {
                $first = $group->sortBy(fn ($r) => $r->order ?? PHP_INT_MAX)->first();
                $levelLabels[$key] = ($first && mb_strlen($first->word) === 1) ? $first->word : null;
            }
        }

        $levelStats = [];
        foreach ($rows as $row) {
            foreach ($row['word']?->categories ?? [] as $category) {
                $level = $category->pivot->level;
                $key = $category->id . '-' . $level;
                $levelStats[$key] ??= [
                    'category' => $category,
                    'level' => $level,
                    'label' => $levelLabels[$key] ?? null,
                    'scores' => [],
                ];
                $levelStats[$key]['scores'][] = $row['score'];
            }
        }

        $improvingScore = (int) config('services.word_progress.improving_score', 5);

        return collect($levelStats)
            ->map(function ($entry) use ($improvingScore) {
                $avg = array_sum($entry['scores']) / count($entry['scores']);
                return [
                    'category' => $entry['category'],
                    'level' => $entry['level'],
                    'label' => $entry['label'],
                    'word_count' => count($entry['scores']),
                    'avg_score' => round($avg, 1),
                    'status' => $avg >= 8 ? 'doing_great' : ($avg >= $improvingScore ? 'improving' : 'needs_focus'),
                ];
            })
            ->values();
    }
}
