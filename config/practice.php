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

        // Misses on one item in a session before it is set aside for the day
        // and put on the admin list to work through together.
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
        'warm_up' => 0.25,
        'core'    => 0.50,
        'stretch' => 0.05,
        // Whatever is left is the close, and the final item is always a win.
    ],

    /*
     | Accuracy bands, as fractions. Core is deliberately the middle: an item
     | he already has teaches nothing, and one he cannot reach teaches less.
     |
     | These were tuned by simulating whole sittings against his real per-item
     | accuracy, not picked off a chart. The literature's target is 70–80%
     | session success; this shape reaches about 64%, and pushing warm-up
     | higher does not move it, because he does not yet hold enough items
     | above 65% to fill more of a sitting without repeating them. It should
     | climb on its own as items improve. The comparison that matters is the
     | 35% he was actually living with.
     */
    'bands' => [
        'warm_up_min' => 0.65,
        'core_min'    => 0.55,
        'core_max'    => 0.85,
        'stretch_min' => 0.25,

        // Below this an item stops appearing in solo practice and waits for a
        // session with a person sitting next to him.
        'quarantine_below' => 0.25,

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
