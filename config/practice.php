<?php

/**
 * How a practice session is put together.
 *
 * These are the numbers a therapist would want to change without touching
 * code. Nothing here names a category: the engine reads the same knobs for
 * letters, words and phrases, and derives everything else from the attempt
 * log. Per-category overrides live under 'categories', keyed by slug.
 */
return [

    /*
     | The wall rules. Re-showing a missed item is not itself the problem —
     | one retry is ordinary practice. The damage came from re-showing it
     | without limit: the log holds 450 runs of five or more consecutive
     | misses, and one unbroken run of 61 that ended one evening and was still
     | going the next morning.
     */
    'misses' => [
        // Retries allowed on the spot before the engine moves on.
        'retries' => 1,

        // Misses on one item within a block before it is set aside and put on
        // the admin list to work through together. Note this resets with the
        // block, like every other limit here — an item set aside can come back
        // after a long enough break. What keeps a genuinely hard item out of
        // solo practice across blocks is the quarantine band, which is
        // measured over 30 days rather than over the sitting.
        'set_aside_after' => 4,

        // Consecutive misses across any items before the engine stops working
        // the plan and puts a win in front of him. Moving on after two misses
        // fixes the same-item loop but not the run that walks item to item —
        // simulated against his real per-item accuracy, the shape alone still
        // produced runs of twelve. At 3 the worst run fell to 3; at 2 it fell
        // to 2 and session success rose from 52% to 63%.
        'recover_after' => 2,
    ],

    /*
     | Session size. Median sitting is 85 attempts; the top tenth run past 200
     | and the worst was 330. At his accuracy that is well over a hundred
     | failures in a row of chairs, which is where a session stops helping.
     */
    'session' => [
        // Both are per block, not per day: a pause longer than gap_minutes
        // starts a fresh sitting with the counters back at zero. Nothing here
        // stops him doing several blocks in a day, which is deliberate —
        // little and often is the shape that helps, and he already turns up
        // 4 to 7 days a week without being asked.
        'max_attempts' => 50,
        'max_minutes'  => 20,

        // A pause longer than this starts a new sitting.
        'gap_minutes'  => 30,

        // How far back to read attempts when working out the current sitting
        // and each item's miss streak. Bounded so a long history does not make
        // every request slower.
        'recent_attempt_window' => 600,

        // A session that ends on a miss is extended by up to this many
        // guaranteed wins, so the last thing that happens is never a failure.
        'max_closing_extensions' => 3,
    ],

    /*
     | The shape of a sitting, as proportions of its length. Open on something
     | he gets, spend the middle where learning happens, allow one stretch,
     | and always close on a win — behavioural momentum, and it decides what
     | he remembers about the session afterwards.
     */
    'shape' => [
        'warm_up' => 0.20,
        'focus'   => 0.60,
        // Whatever is left is the close, and the final item is always a win.
        // Recovery wins are taken out of the focus share as they are needed
        // rather than being planned, so a hard sitting spends less of itself
        // on the work and an easy one spends more.
    ],

    /*
     | The level being worked. Chosen by what has been left alone longest,
     | among the levels he has not finished — so it rotates by itself, and
     | working ጠ today means something else is the most overdue tomorrow.
     |
     | Difficulty is deliberately not part of the choice. He goes at the hard
     | levels on purpose; an engine that steered around them would be removing
     | the thing he is actually trying to do.
     */
    'focus' => [
        // Misses on one level in a sitting, with nothing landing, before the
        // engine moves to another and leaves this one for a session with
        // someone alongside him.
        'abandon_after_misses' => 5,
    ],

    /*
     | Accuracy bands, as fractions.
     |
     | Note what is not here: a threshold below which an item is withheld.
     | An earlier version of this engine took everything under 25% out of solo
     | practice, which scored well in simulation and was the wrong thing to
     | build — those are precisely the levels he chooses to work on. Hard
     | material stays in. What makes it survivable is the miss rules, not
     | keeping it away from him.
     */
    'bands' => [
        // A win has to be a win: warm-ups, recovery and the close are drawn
        // from items at least this good.
        'support_min' => 0.65,

        // At or above this a level counts as finished and steps aside so the
        // focus can rotate to something still being learned.
        'mastered_above' => 0.85,

        // Not a gate — only the mark used on the admin list to say an item
        // needs someone sitting with him.
        'needs_help_below' => 0.25,

        // An item needs at least this many attempts before its accuracy is
        // treated as real rather than noise. Below it accuracy reads as null —
        // unknown, which is not the same as bad, and must not be scheduled as
        // though it were.
        'min_attempts' => 4,

        // How far back accuracy is measured. Recent ability, not a lifetime
        // average that a bad first fortnight would hold down forever.
        'window_days' => 30,
    ],

    /*
     | Spaced repetition. Items he has are shown less often; items he is
     | working on come back sooner, but never more than twice in one sitting.
     */
    'spacing' => [
        'max_repeats_per_session' => 2,
        'mastered_rest_days'      => 3,
    ],

    /*
     | Learned confusions (ቃ heard as ካ). Below min_occurrences a pair is
     | treated as noise rather than a real neighbour.
     */
    'confusion' => [
        'min_occurrences' => 5,
        'cache_seconds'   => 900,
    ],

    /*
     | Per-category overrides, keyed by category slug. Same keys as above.
     | Left empty on purpose: the engine should work for a new category the
     | day it is added, without anyone configuring it first.
     */
    'categories' => [],

];
