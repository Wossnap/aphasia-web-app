<?php

namespace App\Services\Practice;

use App\Models\AmharicWord;
use App\Models\Category;
use App\Models\SpeechAttempt;
use Illuminate\Support\Collection;

/**
 * Decides what he practises next, and when a sitting is finished.
 *
 * The harm in the log was never that the material was hard. It was that a
 * miss led back to the same item without limit — re-shown 74% of the time
 * after a failure against 6% after a success — so 450 runs of five or more
 * consecutive misses built up, and one run of 61 that began one afternoon and
 * was still going the next morning.
 *
 * So this does not make practice easier. It picks something to work on, keeps
 * the work in it, and makes sure the work can never turn into a wall: one
 * retry, then move on; a win after a run of misses; never the same sound
 * twice in a row; a visible end; and never finishing on a failure.
 *
 * Nothing here knows what a category contains. Letters, words and phrases go
 * through the same rules, and a category added tomorrow works unchanged.
 */
class SessionPlanner
{
    public function __construct(
        private ItemStats $stats,
        private WordFamily $families,
        private ConfusionGraph $confusions,
    ) {
    }

    /**
     * The next item, or a finished session.
     *
     * @return array{done:bool, item:?array, slot:?string, position:int, total:int, reason:?string, focus_level:?int}
     */
    public function next(?int $userId, Category $category): array
    {
        $settings = $this->settingsFor($category);
        $stats = $this->stats->forCategory($userId, $category);
        $session = $this->stats->currentSession($userId, $category);

        $total = (int) $settings['session']['max_attempts'];
        $position = $session->count() + 1;
        $last = $session->last();
        $lastWasMiss = $last && !$last->is_correct;

        if ($finish = $this->finishIfDone($session, $settings, $position, $total, $lastWasMiss)) {
            return $finish;
        }

        $overrun = $position > $total;

        // Moving on after two misses fixes the loop on one item, but not the
        // run that walks from item to item — the shape alone still produced
        // runs of twelve in simulation. Past a couple of misses in a row the
        // plan stops mattering and he needs a win.
        $recovering = $this->trailingMissRun($session) >= (int) $settings['misses']['recover_after'];

        $slot = ($overrun || $recovering) ? 'close' : $this->slotFor($position, $total, $settings);

        $focus = $this->focusLevel($stats, $session, $settings);

        // One more go at the item he has just missed: ordinary practice, and
        // the point at which the old loop began instead of ended. Recovery
        // outranks it — on a run, another go at what he just failed is the
        // last thing he needs.
        if ($lastWasMiss && !$overrun && !$recovering && $this->retryAllowed($last, $session, $settings)) {
            $retry = $this->present($stats[$last->amharic_word_id] ?? null, $slot, $position, $total, $focus, retry: true);

            if ($retry) {
                return $retry;
            }
        }

        $item = $this->pick($stats, $slot, $last, $lastWasMiss, $userId, $settings, $category, $focus);

        return $this->present($item, $slot, $position, $total, $focus)
            // Nothing eligible is not an error: it means everything here has
            // had its turn today, which is a finished session.
            ?? $this->finished($position, $total, 'exhausted');
    }

    /**
     * The level this sitting is working on.
     *
     * Chosen by what he has left alone longest, among the levels he has not
     * finished. That is what keeps it from serving the same thing every day
     * without anyone having to maintain a rota: working ጠ today makes ጠ the
     * most recently touched, so tomorrow something else is the most overdue.
     * It cycles by itself.
     *
     * Difficulty is deliberately not part of the choice. He goes at the hard
     * levels on purpose, and an engine that steered around them would be
     * taking away the thing he is actually trying to do.
     */
    private function focusLevel(Collection $stats, Collection $session, array $settings): ?int
    {
        $mastered = (float) $settings['bands']['mastered_above'];
        $abandonAfter = (int) $settings['focus']['abandon_after_misses'];

        $levels = $stats
            ->filter(fn ($i) => $i['level'] !== null)
            ->groupBy('level')
            ->map(function ($items, $level) {
                $known = $items->filter(fn ($i) => $i['accuracy'] !== null);

                return [
                    'level' => (int) $level,
                    'accuracy' => $known->isEmpty() ? null : $known->avg('accuracy'),
                    // Never touched sorts oldest, so unseen levels come up
                    // before ones he has already been through. Deliberately
                    // blind to the sitting in progress: otherwise the level
                    // being worked becomes the most recently touched one, and
                    // the focus rotates away from it after every item.
                    'last_worked' => $items->max(fn ($i) => $i['last_worked']?->timestamp) ?? 0,
                    'session_misses' => $items->sum('session_misses'),
                    'session_correct' => $items->sum(fn ($i) => $i['session_attempts'] - $i['session_misses']),
                ];
            });

        $candidates = $levels
            // Finished levels step aside for the ones still being learned.
            ->filter(fn ($l) => $l['accuracy'] === null || $l['accuracy'] <= $mastered)
            // A level that has produced nothing but misses today is not going
            // to come good this sitting. Move to another and let the admin
            // list pick it up for a session with someone alongside him.
            ->filter(fn ($l) => !($l['session_correct'] === 0 && $l['session_misses'] >= $abandonAfter));

        if ($candidates->isEmpty()) {
            $candidates = $levels;
        }

        return $candidates->sortBy('last_worked')->first()['level'] ?? null;
    }

    /** Whether the sitting is over on length or on the clock. */
    private function finishIfDone(Collection $session, array $settings, int $position, int $total, bool $lastWasMiss): ?array
    {
        if ($session->isEmpty()) {
            return null;
        }

        $maxMinutes = (int) $settings['session']['max_minutes'];
        $elapsed = abs($session->first()->created_at->diffInMinutes(now()));
        $overrunBy = $position - $total;
        $allowedOverrun = (int) $settings['session']['max_closing_extensions'];

        $atCap = $position > $total;
        $outOfTime = $elapsed >= $maxMinutes;

        if (!$atCap && !$outOfTime) {
            return null;
        }

        // Never end on a miss. Past the cap it keeps going only long enough to
        // land a win, and only for a few items, so a bad patch at the end
        // cannot extend the sitting indefinitely.
        if ($lastWasMiss && $overrunBy <= $allowedOverrun) {
            return null;
        }

        return $this->finished($position, $total, $outOfTime && !$atCap ? 'time' : 'cap');
    }

    private function finished(int $position, int $total, string $reason): array
    {
        return [
            'done' => true,
            'item' => null,
            'slot' => null,
            'position' => $position,
            'total' => $total,
            'reason' => $reason,
            'focus_level' => null,
        ];
    }

    /**
     * Which part of the sitting this position falls in.
     *
     * Open on something he has, spend the middle on the work, close on a win.
     * The final item is always a close, because it is what he takes away from
     * the session.
     */
    private function slotFor(int $position, int $total, array $settings): string
    {
        if ($position >= $total) {
            return 'close';
        }

        $warmUp = (int) ceil($total * $settings['shape']['warm_up']);
        $focus = $warmUp + (int) ceil($total * $settings['shape']['focus']);

        return match (true) {
            $position <= $warmUp => 'warm_up',
            $position <= $focus => 'focus',
            default => 'close',
        };
    }

    /** Consecutive misses at the end of the sitting so far, across any items. */
    private function trailingMissRun(Collection $session): int
    {
        $run = 0;

        foreach ($session->reverse() as $attempt) {
            if ($attempt->is_correct) {
                break;
            }

            $run++;
        }

        return $run;
    }

    private function retryAllowed(SpeechAttempt $last, Collection $session, array $settings): bool
    {
        $streak = 0;

        foreach ($session->reverse() as $attempt) {
            if ($attempt->amharic_word_id !== $last->amharic_word_id || $attempt->is_correct) {
                break;
            }

            $streak++;
        }

        return $streak <= (int) $settings['misses']['retries'];
    }

    /**
     * Choose an item for this slot.
     *
     * The focus slot is the work and runs through the focus level in the
     * category's own order. Warm-up and close are the wins, and come from
     * elsewhere — in a category whose levels are consonant families, a win
     * has to come from a different sound to be worth anything.
     */
    private function pick(
        Collection $stats,
        string $slot,
        ?SpeechAttempt $last,
        bool $lastWasMiss,
        ?int $userId,
        array $settings,
        Category $category,
        ?int $focus,
    ): ?array {
        $eligible = $this->eligible($stats, $last, $lastWasMiss, $userId, $settings);

        // Spacing gives way only when it would otherwise leave him ending on a
        // failure, and only then. Relaxing it up front pulls the same two or
        // three safe items every time a win is needed.
        if ($eligible->isEmpty() && $lastWasMiss && $slot === 'close') {
            $eligible = $this->eligible($stats, $last, $lastWasMiss, $userId, $settings, relaxRepeats: true);
        }

        if ($eligible->isEmpty()) {
            return null;
        }

        if ($category->worksByLevel() && $focus !== null) {
            $chosen = $slot === 'focus'
                ? $this->fromFocus($eligible, $focus)
                : $this->winFrom($eligible, $focus, $settings);

            if ($chosen) {
                return $chosen;
            }
        }

        return $this->byBand($eligible, $slot, $settings);
    }

    /**
     * The next item of the focus level, in the category's own order.
     *
     * Everything in the level is in play, including what he is poor at: that
     * is the work, and the miss rules are what make it survivable. Ordering is
     * the sequence the alphabet is taught in, so a good run reads ሀ ሁ ሂ ሃ
     * rather than jumping about by score.
     */
    private function fromFocus(Collection $eligible, int $focus): ?array
    {
        return $eligible
            ->where('level', $focus)
            // Work through the row before coming back round it. Ordering on
            // the sequence alone would hand back ሀ again the moment it became
            // eligible, so the sitting would read ሀ ሁ ሀ ሂ ሀ rather than
            // walking the family.
            ->sortBy(fn ($i) => [$i['session_attempts'], $i['order'] ?? PHP_INT_MAX])
            ->first();
    }

    /**
     * A win, taken from outside the level being worked. Strongest first —
     * this is the one place the engine should be picking something easy.
     */
    private function winFrom(Collection $eligible, int $focus, array $settings): ?array
    {
        $elsewhere = $eligible->where('level', '!=', $focus);

        if ($elsewhere->isEmpty()) {
            return null;
        }

        $support = $elsewhere->filter(
            fn ($i) => $i['accuracy'] !== null && $i['accuracy'] >= $settings['bands']['support_min']
        );

        return $this->freshestFirst($support->isNotEmpty() ? $support : $elsewhere);
    }

    /**
     * Strongest first, but spend the ones not yet used in this sitting before
     * coming back to any of them. Sorting on accuracy alone means the same
     * two items alternate all session — ሆ ሒ ሆ ሒ — which is a dull way to
     * give someone their wins.
     */
    private function freshestFirst(Collection $candidates): ?array
    {
        return $candidates
            ->sortBy(fn ($i) => [$i['session_attempts'], -($i['accuracy'] ?? 0)])
            ->first();
    }

    /**
     * Item-by-item selection, for categories not worked a level at a time.
     * Warm-up and close want the surest thing; the middle wants whatever is
     * most overdue, so the category keeps turning over.
     */
    private function byBand(Collection $eligible, string $slot, array $settings): ?array
    {
        if ($slot === 'focus') {
            return $eligible
                ->sortBy(fn ($i) => $i['last_attempt_at']?->timestamp ?? 0)
                ->first();
        }

        $support = $eligible->filter(
            fn ($i) => $i['accuracy'] !== null && $i['accuracy'] >= $settings['bands']['support_min']
        );

        return $this->freshestFirst($support->isNotEmpty() ? $support : $eligible);
    }

    private function eligible(
        Collection $stats,
        ?SpeechAttempt $last,
        bool $lastWasMiss,
        ?int $userId,
        array $settings,
        bool $relaxRepeats = false,
    ): Collection {
        $setAsideAfter = (int) $settings['misses']['set_aside_after'];

        $maxRepeats = $relaxRepeats
            ? PHP_INT_MAX
            : (int) $settings['spacing']['max_repeats_per_session'];

        // Resolved once: reading it inside the filter would load the word
        // again for every item in the category.
        $lastWord = $last?->word?->word;

        return $stats->filter(function (array $item) use (
            $last, $lastWord, $lastWasMiss, $userId, $setAsideAfter, $maxRepeats
        ) {
            // Set aside for this sitting, and on the admin list to work
            // through with someone.
            if ($item['session_misses'] >= $setAsideAfter) {
                return false;
            }

            if ($item['session_attempts'] >= $maxRepeats) {
                return false;
            }

            if (!$last) {
                return true;
            }

            // Straight after a miss, do not serve the same item — the retry is
            // handled before this — nor anything that would land as the same
            // wall: same consonant family, or something his own log shows gets
            // confused with it.
            if ($lastWasMiss) {
                if ($this->families->areSiblings($item['word'], $lastWord)) {
                    return false;
                }

                if ($this->confusions->areSiblings($item['word_id'], $last->amharic_word_id, $userId)) {
                    return false;
                }
            }

            return $item['word_id'] !== $last->amharic_word_id;
        });
    }

    /** @return array{done:bool, item:?array, slot:?string, position:int, total:int, reason:?string, focus_level:?int}|null */
    private function present(?array $item, string $slot, int $position, int $total, ?int $focus, bool $retry = false): ?array
    {
        if (!$item) {
            return null;
        }

        $word = AmharicWord::find($item['word_id']);

        if (!$word) {
            return null;
        }

        return [
            'done' => false,
            'slot' => $slot,
            'position' => $position,
            'total' => $total,
            'reason' => null,
            'focus_level' => $focus,
            'item' => [
                'id' => $word->id,
                'word' => $word->word,
                'level' => $item['level'],
                'transliterations' => $word->transliterations,
                'meaning' => $word->meaning,
                'audio_path' => $word->audio_path,
                'gif_path' => $word->gif_path,
                'image_path' => $word->image_path,
                'show_in_random' => $word->show_in_random,
                'engine' => $word->engine,
                'retry' => $retry,
                'progress' => null,
            ],
        ];
    }

    /**
     * Category settings: the defaults with any per-slug override merged over
     * them. Overrides are configuration rather than code, so the engine never
     * grows a branch for one category.
     */
    private function settingsFor(Category $category): array
    {
        $defaults = config('practice');
        $override = $defaults['categories'][$category->slug] ?? [];

        return array_replace_recursive($defaults, $override);
    }
}
