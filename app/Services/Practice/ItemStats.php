<?php

namespace App\Services\Practice;

use App\Models\Category;
use App\Models\SpeechAttempt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What we know about every item in a category, for one person.
 *
 * Everything the engine decides comes from here, and everything here comes
 * from the attempt log — no new tables, no scores kept in parallel that can
 * drift away from what actually happened. That also means a rescore
 * immediately changes what the engine does, which is the point of having
 * fixed the scoring first.
 */
class ItemStats
{
    /**
     * Every item in the category with this user's history against it.
     *
     * @return Collection<int, array> keyed by word id
     */
    public function forCategory(?int $userId, Category $category): Collection
    {
        $items = $category->words()->get(['amharic_words.id', 'amharic_words.word', 'amharic_words.order']);
        $wordIds = $items->pluck('id')->all();

        if ($wordIds === []) {
            return collect();
        }

        $windowDays = (int) config('practice.bands.window_days', 30);
        $since = Carbon::now()->subDays($windowDays);

        $recent = $this->recentAttempts($userId, $wordIds);
        $sessionStart = $this->sessionStart($recent);
        $totals = $this->totals($userId, $wordIds, $since, $sessionStart);

        return $items->mapWithKeys(function ($item) use ($totals, $recent, $sessionStart, $category) {
            $total = $totals[$item->id] ?? null;
            $attempts = (int) ($total->attempts ?? 0);
            $correct = (int) ($total->correct ?? 0);
            $minAttempts = (int) config('practice.bands.min_attempts', 4);

            $forItem = $recent->where('amharic_word_id', $item->id);
            $inSession = $sessionStart
                ? $forItem->filter(fn ($a) => $a->created_at->greaterThanOrEqualTo($sessionStart))
                : collect();

            return [$item->id => [
                'word_id' => $item->id,
                'word' => $item->word,
                'level' => $item->pivot->level ?? null,
                // The category's own sequence — ሀ ሁ ሂ ሃ ሄ ህ ሆ. Practice runs
                // in this order because that is how the alphabet is learned
                // and recited, and the vowel row is itself a scaffold.
                'order' => $item->order,
                'category_id' => $category->id,
                'attempts' => $attempts,
                'correct' => $correct,
                // Null rather than 0 when there is too little history: an item
                // he has never tried is unknown, not bad, and the two must not
                // be scheduled the same way.
                'accuracy' => $attempts >= $minAttempts ? $correct / $attempts : null,
                'last_attempt_at' => isset($total->last_attempt_at) ? Carbon::parse($total->last_attempt_at) : null,
                // When it was last worked before today's sitting began — the
                // clock the focus rotation runs on.
                'last_worked' => isset($total->last_worked) ? Carbon::parse($total->last_worked) : null,
                'miss_streak' => $this->missStreak($forItem),
                'session_attempts' => $inSession->count(),
                'session_misses' => $inSession->where('is_correct', false)->count(),
            ]];
        });
    }

    /**
     * When the current sitting began, or null if he is not in one.
     *
     * A sitting is bounded by a pause rather than by a calendar day: the log
     * has sessions that end late one evening and resume the next morning, and
     * treating those as one run is how a miss streak came to be 61 long.
     */
    public function sessionStart(?Collection $recent = null): ?Carbon
    {
        $recent ??= collect();

        if ($recent->isEmpty()) {
            return null;
        }

        $gap = (int) config('practice.session.gap_minutes', 30);

        // Newest first: walk back until the pause between two attempts is
        // longer than the gap, and the later of the two starts this sitting.
        $ordered = $recent->sortByDesc('created_at')->values();
        $start = $ordered->first()->created_at;

        // Yesterday's sitting is not this one. Without this, a fresh session
        // opens already at position 31 and every item reads as having been
        // drilled to its per-session limit, so the engine has nothing left to
        // serve and calls the day finished before it has begun.
        if (abs($start->diffInMinutes(now())) > $gap) {
            return null;
        }

        foreach ($ordered as $index => $attempt) {
            $next = $ordered->get($index + 1);

            // abs(): the list runs newest first, and a signed diff would read
            // every gap as negative and never split a sitting.
            if (!$next || abs($attempt->created_at->diffInMinutes($next->created_at)) > $gap) {
                return $start;
            }

            $start = $next->created_at;
        }

        return $start;
    }

    /**
     * Attempts in the sitting that is running now, across all items.
     *
     * @return Collection<int, SpeechAttempt>
     */
    public function currentSession(?int $userId, Category $category): Collection
    {
        $wordIds = $category->words()->pluck('amharic_words.id')->all();

        if ($wordIds === []) {
            return collect();
        }

        $recent = $this->recentAttempts($userId, $wordIds);
        $start = $this->sessionStart($recent);

        if (!$start) {
            return collect();
        }

        return $recent
            ->filter(fn ($a) => $a->created_at->greaterThanOrEqualTo($start))
            ->sortBy('created_at')
            ->values();
    }

    /** Consecutive misses ending at the most recent attempt. */
    private function missStreak(Collection $forItem): int
    {
        $streak = 0;

        foreach ($forItem->sortByDesc('created_at') as $attempt) {
            if ($attempt->is_correct) {
                break;
            }

            $streak++;
        }

        return $streak;
    }

    /** @return array<int, object> keyed by word id */
    private function totals(?int $userId, array $wordIds, Carbon $since, ?Carbon $sessionStart): array
    {
        // last_worked deliberately stops at the start of the current sitting.
        // The focus rotates on what has been left alone longest, and that is a
        // question about days: counting the sitting in progress would make the
        // level he is working the most recently touched one, and the engine
        // would rotate away from it after every single item.
        return SpeechAttempt::query()
            ->whereIn('amharic_word_id', $wordIds)
            ->where('created_at', '>=', $since)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->groupBy('amharic_word_id')
            ->select([
                'amharic_word_id',
                DB::raw('COUNT(*) as attempts'),
                DB::raw('SUM(is_correct) as correct'),
                DB::raw('MAX(created_at) as last_attempt_at'),
            ])
            ->when(
                $sessionStart,
                fn ($q) => $q->selectRaw('MAX(CASE WHEN created_at < ? THEN created_at END) as last_worked', [$sessionStart]),
                fn ($q) => $q->selectRaw('MAX(created_at) as last_worked'),
            )
            ->get()
            ->keyBy('amharic_word_id')
            ->all();
    }

    /**
     * A bounded tail of recent attempts, enough to see the current sitting and
     * each item's miss streak without reading the whole log on every request.
     *
     * @return Collection<int, SpeechAttempt>
     */
    private function recentAttempts(?int $userId, array $wordIds): Collection
    {
        return SpeechAttempt::query()
            ->whereIn('amharic_word_id', $wordIds)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->latest('created_at')
            ->limit((int) config('practice.session.recent_attempt_window', 600))
            ->get(['id', 'amharic_word_id', 'is_correct', 'created_at']);
    }
}
