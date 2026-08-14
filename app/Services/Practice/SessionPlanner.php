<?php

namespace App\Services\Practice;

use App\Models\AmharicWord;
use App\Models\Category;
use App\Models\SpeechAttempt;
use Illuminate\Support\Collection;

/**
 * Decides what he practises next, and when a sitting is finished.
 *
 * The app used to re-show a missed item 74% of the time and a cleared one 6%,
 * which meant an item he could not win became a loop he could not leave. This
 * replaces that with a shape: open on something he has, spend the middle where
 * learning happens, allow one stretch, and close on a win.
 *
 * Nothing in here knows what a category contains. Letters, words and phrases
 * go through the same rules; the differences that matter — which items are
 * neighbours, which are hard — are read from the attempt log and from the
 * characters themselves, so a category added tomorrow works without changes.
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
     * @return array{done:bool, item:?array, slot:?string, position:int, total:int, reason:?string}
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

        // A session that has run past its cap only continues to put a win at
        // the end of it; the slot is forced, whatever the shape would say.
        $overrun = $position > $total;

        // Moving on after two misses fixes the loop on one item, but not the
        // run that walks from item to item — the plan alone still produced
        // runs of twelve in simulation. Past a few misses in a row the shape
        // stops mattering and he needs a win.
        $recovering = $this->trailingMissRun($session) >= (int) $settings['misses']['recover_after'];

        $slot = ($overrun || $recovering) ? 'close' : $this->slotFor($position, $total, $settings);

        // The retry that is ordinary practice: one more go at the item he has
        // just missed. The second miss is where the old loop began, so that is
        // where the engine moves on instead. Recovery outranks it: on a run,
        // another go at the thing he just missed is the last thing he needs.
        if ($lastWasMiss && !$overrun && !$recovering && $this->retryAllowed($last, $session, $settings)) {
            return $this->present($stats[$last->amharic_word_id] ?? null, $slot, $position, $total, retry: true)
                ?? $this->present($this->pick($stats, $session, $slot, $last, $lastWasMiss, $userId, $settings), $slot, $position, $total);
        }

        $item = $this->pick($stats, $session, $slot, $last, $lastWasMiss, $userId, $settings);

        return $this->present($item, $slot, $position, $total)
            // Nothing eligible is not a failure state: it means everything in
            // this category is either quarantined or already done today, which
            // is a finished session, not an error.
            ?? $this->finished($position, $total, 'exhausted');
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

        // Never end on a miss. Past the cap the engine keeps going only long
        // enough to land a win, and only for a few items, so a bad patch at
        // the end cannot extend the sitting indefinitely.
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
        ];
    }

    /**
     * Which part of the sitting position falls in.
     *
     * The final item is always a close, so the last thing he does is something
     * he can do — it is what he takes away from the session.
     */
    private function slotFor(int $position, int $total, array $settings): string
    {
        if ($position >= $total) {
            return 'close';
        }

        $shape = $settings['shape'];
        $warmUp = (int) ceil($total * $shape['warm_up']);
        $core = $warmUp + (int) ceil($total * $shape['core']);
        $stretch = $core + (int) ceil($total * $shape['stretch']);

        return match (true) {
            $position <= $warmUp => 'warm_up',
            $position <= $core => 'core',
            $position <= $stretch => 'stretch',
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
        $retries = (int) $settings['misses']['retries'];

        // Consecutive misses on this item at the end of the sitting so far.
        $streak = 0;

        foreach ($session->reverse() as $attempt) {
            if ($attempt->amharic_word_id !== $last->amharic_word_id || $attempt->is_correct) {
                break;
            }

            $streak++;
        }

        return $streak <= $retries;
    }

    /**
     * Choose an item for this slot.
     *
     * Eligibility comes first and is the same for every slot; the slot only
     * decides which band is preferred and in what order. When a band is empty
     * the fallback is always towards easier, never harder — a thin core is a
     * reason to warm up more, not to reach further.
     */
    private function pick(
        Collection $stats,
        Collection $session,
        string $slot,
        ?SpeechAttempt $last,
        bool $lastWasMiss,
        ?int $userId,
        array $settings,
    ): ?array {
        $eligible = $this->eligible($stats, $slot, $last, $lastWasMiss, $userId, $settings);

        if ($eligible->isEmpty()) {
            return null;
        }

        foreach ($this->bandsFor($slot) as $band) {
            $candidates = $this->inBand($eligible, $band, $settings);

            if ($candidates->isNotEmpty()) {
                return $this->best($candidates, $band);
            }
        }

        // Every band empty but something is eligible: take the safest thing
        // available rather than refusing to continue.
        return $this->best($eligible, 'warm_up');
    }

    /**
     * Bands to try for a slot, in order, each falling back towards easier.
     *
     * @return array<int, string>
     */
    private function bandsFor(string $slot): array
    {
        return match ($slot) {
            'warm_up' => ['warm_up', 'core'],
            'core' => ['core', 'warm_up'],
            'stretch' => ['stretch', 'core', 'warm_up'],
            'close' => ['warm_up', 'core'],
        };
    }

    private function eligible(
        Collection $stats,
        string $slot,
        ?SpeechAttempt $last,
        bool $lastWasMiss,
        ?int $userId,
        array $settings,
    ): Collection {
        $quarantineBelow = (float) $settings['bands']['quarantine_below'];
        $setAsideAfter = (int) $settings['misses']['set_aside_after'];
        $maxRepeats = (int) $settings['spacing']['max_repeats_per_session'];

        // Closing after a miss is the one time spacing gives way. The rule
        // exists so a handful of easy items do not fill the sitting; it must
        // not be the reason he is left ending the day on a failure because
        // everything he can actually do has already had its two turns.
        if ($lastWasMiss && $slot === 'close') {
            $maxRepeats = PHP_INT_MAX;
        }

        // Resolved once: reading it inside the filter would load the word
        // again for every item in the category.
        $lastWord = $last?->word?->word;

        return $stats->filter(function (array $item) use (
            $last, $lastWord, $lastWasMiss, $userId, $quarantineBelow, $setAsideAfter, $maxRepeats
        ) {
            // Set aside for the day, and on the admin list to work through
            // with a person rather than alone.
            if ($item['session_misses'] >= $setAsideAfter) {
                return false;
            }

            // Twice in a sitting is enough; a third go is drilling, not
            // spacing.
            if ($item['session_attempts'] >= $maxRepeats) {
                return false;
            }

            // Out of solo practice until it has been worked through together.
            if ($item['accuracy'] !== null && $item['accuracy'] < $quarantineBelow) {
                return false;
            }

            if (!$last) {
                return true;
            }

            // Straight after a miss, do not serve the same item (the retry is
            // handled before this) nor anything that would land as the same
            // wall: same consonant family, or something his log shows gets
            // confused with it.
            if ($lastWasMiss) {
                if ($item['word_id'] === $last->amharic_word_id) {
                    return false;
                }

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

    private function inBand(Collection $eligible, string $band, array $settings): Collection
    {
        $bands = $settings['bands'];

        return $eligible->filter(function (array $item) use ($band, $bands, $settings) {
            $accuracy = $item['accuracy'];

            // Unknown items belong in the core: not yet shown to be hard, and
            // the middle is where something new should be met.
            if ($accuracy === null) {
                return $band === 'core';
            }

            return match ($band) {
                'warm_up' => $accuracy >= $bands['warm_up_min'] && $this->isRested($item, $settings),
                'core' => $accuracy >= $bands['core_min'] && $accuracy <= $bands['core_max'],
                'stretch' => $accuracy >= $bands['stretch_min'] && $accuracy < $bands['core_min'],
                default => false,
            };
        });
    }

    /**
     * Spaced repetition for items he already has: they come back at a rest
     * interval rather than every sitting, which is what leaves room for the
     * ones he is actually working on.
     */
    private function isRested(array $item, array $settings): bool
    {
        $bands = $settings['bands'];

        if ($item['accuracy'] === null || $item['accuracy'] <= $bands['core_max']) {
            return true;
        }

        if (!$item['last_attempt_at']) {
            return true;
        }

        return abs($item['last_attempt_at']->diffInDays(now())) >= (int) $settings['spacing']['mastered_rest_days'];
    }

    /**
     * The best candidate within a band.
     *
     * Warm-up and close want the surest thing; core and stretch want the one
     * most due, so the whole category keeps turning over instead of the same
     * handful appearing every sitting.
     */
    private function best(Collection $candidates, string $band): array
    {
        $sorted = match ($band) {
            'warm_up', 'close' => $candidates->sortByDesc(fn ($i) => $i['accuracy'] ?? 0),
            'stretch' => $candidates->sortByDesc(fn ($i) => $i['accuracy'] ?? 0),
            default => $candidates->sortBy(fn ($i) => $i['last_attempt_at']?->timestamp ?? 0),
        };

        return $sorted->first();
    }

    /** @return array{done:bool, item:?array, slot:?string, position:int, total:int, reason:?string}|null */
    private function present(?array $item, string $slot, int $position, int $total, bool $retry = false): ?array
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
            'item' => [
                'id' => $word->id,
                'word' => $word->word,
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
     * Category settings: the defaults, with any per-slug override merged over
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
