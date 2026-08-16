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
        private RowSounds $sounds,
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

        $overrun = $position > $total;

        // Moving on after two misses fixes the loop on one item, but not the
        // run that walks from item to item — the shape alone still produced
        // runs of twelve in simulation. Past a couple of misses in a row the
        // plan stops mattering and he needs a win.
        $recovering = $this->trailingMissRun($session) >= (int) $settings['misses']['recover_after'];

        if ($category->worksByLevel()) {
            return $this->nextByLevel($stats, $session, $settings, $position, $total, $last, $lastWasMiss, $recovering, $overrun, $userId, $category);
        }

        if ($finish = $this->finishIfDone($session, $settings, $position, $total, $lastWasMiss)) {
            return $finish;
        }

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
     * A sitting in a category worked level by level.
     *
     * The session is a playlist of whole levels, each finished in its own
     * order before the next begins — an easy one to open, a hard one for the
     * work, a middling one, an easy one to finish on. That is what "level by
     * level" means: ሀ ሁ ሂ ሃ ሄ ህ ሆ, then ገ ጉ ጊ ጋ ጌ ግ ጎ, and not one letter
     * pulled from each of three different families.
     *
     * A miss still buys one retry and then a breather from a level he has
     * already finished, because in this category a level is one consonant
     * family and carrying on through it would be walking him further into the
     * sound he just missed. After the breather the level resumes exactly
     * where it left off.
     */
    private function nextByLevel(
        Collection $stats,
        Collection $session,
        array $settings,
        int $position,
        int $total,
        ?SpeechAttempt $last,
        bool $lastWasMiss,
        bool $recovering,
        bool $overrun,
        ?int $userId,
        Category $category,
    ): array {
        $mixEasy = $category->mixesEasyLevels();
        $soundOf = $this->sounds->groups($category);
        $playlist = $this->levelPlaylist($stats, $settings, $mixEasy, $soundOf);
        $segment = $this->currentSegment($playlist, $stats, $settings);
        $current = $segment['level'] ?? null;

        // Where rows are walked, the close is a row too — reserving four loose
        // wins in a category set to whole rows would be the engine arguing
        // with its own setting. A row's length is read off the category, so it
        // is seven for fidel and three for a category of three words without
        // anyone configuring it.
        $reserve = $mixEasy
            ? (int) $settings['session']['closing_reserve']
            : $this->typicalRowLength($stats);

        $closing = $position > $total - $reserve;
        $closeLevel = ($closing && !$mixEasy) ? $this->closingLevel($stats, $settings, $soundOf) : null;

        // The cap is a target, not a guillotine. Cutting a row in half to hit
        // a round number is the opposite of working a level at a time, so once
        // past it the engine finishes the row it is on before stopping — and a
        // ceiling keeps that from running away.
        // "Under way" matters: past the cap it finishes the row he is in the
        // middle of, but does not open a fresh one. Without that it would
        // start another closing row every time one completed and only stop at
        // the ceiling.
        $closeRow = $closeLevel !== null ? $stats->where('level', $closeLevel) : collect();

        $rowUnfinished = $closeRow->sum('session_attempts') > 0
            && $closeRow->filter(fn ($i) => $i['session_attempts'] === 0)->isNotEmpty();

        // The close is one row, and it ends when that row is finished — not
        // after a fixed count, which would cut the row in half again. Without
        // this the close simply kept choosing rows: a sitting finished
        // ሀ ሁ ሂ ሃ ሄ ህ ሆ and went straight on to ሐ ሑ ሒ ሓ ሔ ሕ ሖ, a second row
        // and the same /h/ over again.
        $lastLevel = $last ? ($stats[$last->amharic_word_id]['level'] ?? null) : null;

        // The row the close actually opened on — read from the attempts made
        // since the close began, not from whatever row happens to be last. A
        // row finished earlier in the sitting must not count as the close
        // having already happened.
        $openedOn = $session->slice(max(0, $total - $reserve))->first();
        $openedLevel = $openedOn ? ($stats[$openedOn->amharic_word_id]['level'] ?? null) : null;
        $openedRow = $openedLevel !== null ? $stats->where('level', $openedLevel) : collect();

        $closeDone = $closing
            && $openedRow->isNotEmpty()
            && $openedRow->filter(fn ($i) => $i['session_attempts'] === 0)->isEmpty();

        if ($finish = $this->finishIfDone($session, $settings, $position, $total, $lastWasMiss, $rowUnfinished && !$closeDone, $closeDone)) {
            return $finish;
        }

        // One more go at what he just missed, before anything else moves —
        // except in the closing stretch, where another attempt at the thing he
        // has just failed is precisely what the close exists to avoid.
        if ($lastWasMiss && !$overrun && !$recovering && !$closing && $this->retryAllowed($last, $session, $settings)) {
            $retry = $this->present($stats[$last->amharic_word_id] ?? null, 'focus', $position, $total, $current, retry: true);

            if ($retry) {
                return $retry;
            }
        }

        // Inside a level the sibling rule is suspended, because working ገ ጉ ጊ
        // in sequence is the whole point of level by level and every one of
        // them is the same consonant. What bounds a bad level here is not
        // stepping away sound by sound but the playlist itself: a hard level
        // is followed by a middling one and then an easy one, and a level
        // producing nothing at all is abandoned partway.
        $withinLevel = $this->eligible($stats, $last, lastWasMiss: false, userId: $userId, settings: $settings, soundOf: $soundOf);

        // Starting a fresh row while he is on a run of misses is how a sitting
        // opens ሰ✗ ሰ✗ ሱ✗ ኸ✗ ኹ✗ ኺ✗ — two abandoned rows back to back, six
        // failures before anything landed. Carrying on through a row he is
        // already inside is fine, and the abandon rule bounds that; beginning
        // a new one is not, so a win comes first.
        $startingNewRow = $current !== null && $current !== $lastLevel;

        if ($recovering && $startingNewRow && !$overrun && !$closing) {
            // Deliberately not excluding the upcoming row here: if the next
            // row is one he is strong in, opening it is the win. What must be
            // avoided is the row he has just been missing in.
            $win = $this->breather(
                $this->eligible($stats, $last, $lastWasMiss, $userId, $settings, soundOf: $soundOf),
                null,
                $playlist,
                $stats,
                $settings,
                $lastLevel,
                $soundOf,
            );

            // A preference, not a block: if there is no honest win to be had,
            // getting on with the next row beats ending the sitting here.
            if ($win) {
                return $this->present($win, 'close', $position, $total, $current);
            }
        }

        if ($current !== null && !$overrun && !$closing) {
            $item = $this->fromFocus($withinLevel, $current);

            if ($item) {
                return $this->present($item, 'focus', $position, $total, $current);
            }
        }

        // The closing row, walked in order like any other — but the recovery
        // rule still applies inside it. Without that check the close walked
        // straight through a run of misses: his ቢ ባ ቤ ብ ቦ ended a real sitting
        // with five failures in a row, which is the exact thing the engine
        // exists to prevent, happening in the part meant to protect him from
        // it.
        // Skipped only when the closing row is the row he is missing in. A run
        // of misses somewhere else is no reason to abandon the close and drop
        // through to picking loose letters by score, which is how a sitting
        // ended on ቦ — the last letter of the በ row offered as its opening.
        if ($closeLevel !== null && !($recovering && $closeLevel === $lastLevel)) {
            $item = $this->fromFocus($withinLevel, $closeLevel);

            if ($item) {
                return $this->present($item, 'close', $position, $total, $closeLevel);
            }
        }

        // A mixed place: a short run of wins drawn from the easy levels rather
        // than a row walked through. Same items, spread out instead of
        // grouped, which is the only difference the two settings make.
        // Loose letters chosen by score belong to the mixed setting only. In a
        // category walking whole rows this path was still reachable during the
        // close, and it is what produced ቦ — the end of the በ row served as
        // its start. Rows mode falls through to the breather instead, which
        // opens a row at its beginning.
        if ((($closing && $mixEasy) || ($segment && $segment['type'] === 'mixed')) && !$overrun) {
            $win = $this->fromEasyLevels(
                $this->eligible($stats, $last, $lastWasMiss, $userId, $settings, soundOf: $soundOf),
                $stats,
                $settings,
                $last ? ($stats[$last->amharic_word_id]['level'] ?? null) : null,
            );

            if ($win) {
                return $this->present($win, $closing ? 'close' : 'warm_up', $position, $total, null);
            }
        }

        // Between levels, and at the end of the sitting: a win. Here the
        // sibling rule applies again, since this is meant to be a change of
        // sound as well as a change of level.
        $eligible = $this->eligible($stats, $last, $lastWasMiss, $userId, $settings, soundOf: $soundOf);

        if ($eligible->isEmpty()) {
            $eligible = $this->eligible($stats, $last, $lastWasMiss, $userId, $settings, relaxRepeats: true, soundOf: $soundOf);
        }

        $breather = $this->breather($eligible, $current, $playlist, $stats, $settings, $lastWasMiss ? $lastLevel : null, $soundOf);

        return $this->present($breather, 'close', $position, $total, $current)
            ?? $this->finished($position, $total, 'exhausted');
    }

    /**
     * The order the levels are worked in this sitting.
     *
     * Built from the shape in config — easy, hard, medium, easy — with each
     * place filled by the level of that difficulty he has left alone longest,
     * so which particular hard level comes up rotates from day to day without
     * anyone keeping a rota. Difficulty is read from his own accuracy, so a
     * level that becomes easy stops being served as work.
     *
     * @return array<int, int> level numbers, in the order they are worked
     */
    private function levelPlaylist(Collection $stats, array $settings, bool $mixEasy = false, array $soundOf = []): array
    {
        $levels = $this->levelSummary($stats, $settings);

        if ($levels->isEmpty()) {
            return [];
        }

        $byBand = $this->levelsByBand($levels, $settings);

        $shape = $settings['level_shape'];
        $playlist = [];
        $taken = [];
        // ሀ, ሐ and ኀ are three rows and one /h/. Taking all three in a sitting
        // is doing one row three times while appearing to do three.
        $takenSounds = [];

        // Enough places to fill the sitting even if every level goes perfectly.
        $places = max(count($shape), (int) ceil($settings['session']['max_attempts'] / 4));

        for ($place = 0; $place < $places; $place++) {
            $wanted = $shape[$place % count($shape)];

            // Easy levels can be spent as loose wins rather than walked as a
            // row. Blocked practice for what he is learning, mixed for what he
            // already has.
            if ($mixEasy && $wanted === 'easy') {
                $playlist[] = ['type' => 'mixed'];
                continue;
            }

            $level = $this->takeLevel($byBand, $wanted, $taken, skipEasy: $mixEasy, soundOf: $soundOf, takenSounds: $takenSounds);

            if ($level === null) {
                break;
            }

            $taken[$level] = true;

            if (isset($soundOf[$level])) {
                $takenSounds[$soundOf[$level]] = true;
            }
            $playlist[] = ['type' => 'level', 'level' => $level];
        }

        return $playlist;
    }

    /** @return array<string, Collection> levels per difficulty, longest left alone first */
    private function levelsByBand(Collection $levels, array $settings): array
    {
        $easyMin = (float) $settings['level_bands']['easy_min'];
        $hardMax = (float) $settings['level_bands']['hard_max'];

        return [
            'easy' => $levels->filter(fn ($l) => $l['accuracy'] !== null && $l['accuracy'] >= $easyMin)->sortBy('last_worked')->values(),
            'hard' => $levels->filter(fn ($l) => $l['accuracy'] !== null && $l['accuracy'] < $hardMax)->sortBy('last_worked')->values(),
            // Unknown counts as middling: too little history to say is not the
            // same as known to be hard.
            'medium' => $levels->filter(
                fn ($l) => $l['accuracy'] === null || ($l['accuracy'] >= $hardMax && $l['accuracy'] < $easyMin)
            )->sortBy('last_worked')->values(),
        ];
    }

    /**
     * The next unused level of the wanted difficulty, falling back through the
     * other bands so a category with no hard levels left still fills its
     * playlist rather than cutting the sitting short.
     */
    private function takeLevel(
        array $byBand,
        string $wanted,
        array $taken,
        bool $skipEasy = false,
        array $soundOf = [],
        array $takenSounds = [],
    ): ?int {
        $order = match ($wanted) {
            'easy' => ['easy', 'medium', 'hard'],
            'hard' => ['hard', 'medium', 'easy'],
            default => ['medium', 'hard', 'easy'],
        };

        // When easy levels are being spent as loose wins they must not also
        // turn up as a row through a fallback.
        if ($skipEasy) {
            $order = array_values(array_diff($order, ['easy']));
        }

        foreach ($order as $band) {
            foreach ($byBand[$band] as $level) {
                $id = $level['level'];
                $sound = $soundOf[$id] ?? null;

                if (isset($taken[$id]) || ($sound !== null && isset($takenSounds[$sound]))) {
                    continue;
                }

                return $id;
            }
        }

        // Every remaining row repeats a sound already in the sitting. Better a
        // repeated sound than a short session, so take the first free row.
        foreach ($order as $band) {
            foreach ($byBand[$band] as $level) {
                if (!isset($taken[$level['level']])) {
                    return $level['level'];
                }
            }
        }

        return null;
    }

    /**
     * Which level of the playlist he is on: the first one still holding an
     * item he has not met this sitting. Recomputed from the attempt log on
     * every request rather than remembered, so nothing has to be kept in
     * sync and a reloaded page resumes exactly where it was.
     */
    private function currentSegment(array $playlist, Collection $stats, array $settings): ?array
    {
        $setAsideAfter = (int) $settings['misses']['set_aside_after'];
        $abandonAfter = (int) $settings['focus']['abandon_after_misses'];
        $winRun = (int) $settings['mixed_win_run'];

        // How much easy work has already been spent this sitting. Each mixed
        // place consumes a run of it, so counting attempts is enough to know
        // which places are behind him — no session state to keep in sync, and
        // a reloaded page lands in the same spot.
        $easyLevels = $this->levelsByBand($this->levelSummary($stats, $settings), $settings)['easy']
            ->pluck('level')
            ->all();

        $easySpent = $stats->whereIn('level', $easyLevels)->sum('session_attempts');

        foreach ($playlist as $segment) {
            if ($segment['type'] === 'mixed') {
                if ($easySpent >= $winRun) {
                    $easySpent -= $winRun;
                    continue;
                }

                return $segment;
            }

            $level = $segment['level'];
            $items = $stats->where('level', $level);

            if ($items->isEmpty()) {
                continue;
            }

            // A level going badly today is not going to come good by being
            // pushed through to the end of the row. Move on, and let the admin
            // list pick it up for a session with someone alongside him.
            //
            // Counted on misses alone, not on "nothing landed at all": one
            // letter going in early does not mean the rest of the family is
            // working, and requiring a blank level let a run of eight through.
            if ($items->sum('session_misses') >= $abandonAfter) {
                continue;
            }

            $unmet = $items->filter(
                fn ($i) => $i['session_attempts'] === 0 && $i['session_misses'] < $setAsideAfter
            );

            if ($unmet->isNotEmpty()) {
                return $segment;
            }
        }

        return null;
    }

    /**
     * How long a row is in this category, taken from the category itself: the
     * commonest level size. Seven for fidel, three for a category of three
     * words, and nothing to configure either way.
     */
    private function typicalRowLength(Collection $stats): int
    {
        $sizes = $stats
            ->filter(fn ($i) => $i['level'] !== null)
            ->groupBy('level')
            ->map->count();

        return $sizes->isEmpty() ? 1 : (int) max(1, $sizes->median());
    }

    /**
     * The easy row the sitting closes on.
     *
     * Finish one already under way before starting another, so this answers
     * the same on every request while the row is being walked — otherwise the
     * close would jump to a different family after each item.
     */
    private function closingLevel(Collection $stats, array $settings, array $soundOf = []): ?int
    {
        $abandonAfter = (int) $settings['focus']['abandon_after_misses'];
        $easyLevels = $this->levelsByBand($this->levelSummary($stats, $settings), $settings)['easy'];

        if ($easyLevels->isEmpty()) {
            return null;
        }

        $rows = $easyLevels
            ->map(function ($level) use ($stats) {
                $items = $stats->where('level', $level['level']);

                return $level + [
                    'attempted' => $items->sum('session_attempts'),
                    'missed' => $items->sum('session_misses'),
                    'unmet' => $items->filter(fn ($i) => $i['session_attempts'] === 0)->count(),
                ];
            })
            // A level that is easy on paper but going badly today is not a
            // close. His በ family is a 78% row, and on the day it mattered he
            // had already missed በ በ ቡ before the close resumed it and handed
            // him five more. Today's evidence outranks the average.
            ->filter(fn ($r) => $r['missed'] < $abandonAfter);

        if ($rows->isEmpty()) {
            return null;
        }

        // How much each sound has already had. Closing on ሐ after a sitting
        // spent on ሀ is closing on the same /h/ he has been doing all along —
        // and a single letter of ሐ served as a win earlier is enough to make
        // that row look "under way" and win the close outright.
        $soundUse = [];

        foreach ($stats as $item) {
            $sound = $soundOf[$item['level']] ?? null;

            if ($sound !== null) {
                $soundUse[$sound] = ($soundUse[$sound] ?? 0) + $item['session_attempts'];
            }
        }

        $spent = (int) max(1, $this->typicalRowLength($stats));

        $fresh = $rows->filter(fn ($r) => ($soundUse[$soundOf[$r['level']] ?? -1] ?? 0) < $spent);

        if ($fresh->isNotEmpty()) {
            $rows = $fresh;
        }

        $underway = $rows->filter(fn ($r) => $r['attempted'] > 0 && $r['unmet'] > 0);

        if ($underway->isNotEmpty()) {
            return $underway->sortByDesc('attempted')->first()['level'];
        }

        $untouched = $rows->filter(fn ($r) => $r['attempted'] === 0);

        return ($untouched->isNotEmpty() ? $untouched : $rows)
            ->sortBy(fn ($r) => [$soundUse[$soundOf[$r['level']] ?? -1] ?? 0, $r['last_worked']])
            ->first()['level'] ?? null;
    }

    /**
     * One letter from the easy levels, for a mixed place.
     *
     * Spread across families rather than taken from one, so a run of four
     * wins is four different sounds. That interleaving is the whole point of
     * the setting: the same letters he would have walked as a row, met in a
     * mixed order instead.
     */
    private function fromEasyLevels(Collection $eligible, Collection $stats, array $settings, ?int $lastLevel): ?array
    {
        $easyLevels = $this->levelsByBand($this->levelSummary($stats, $settings), $settings)['easy']
            ->pluck('level')
            ->all();

        $candidates = $this->winnable($eligible, $stats, $settings)->whereIn('level', $easyLevels);

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates
            ->sortBy(fn ($i) => [
                // Spend the untouched ones first,
                $i['session_attempts'],
                // then step to a different family — without this the run
                // simply takes the strongest items, which all belong to one
                // family, and nothing is interleaved at all,
                $i['level'] === $lastLevel ? 1 : 0,
                // and among equals, the surest thing.
                -($i['accuracy'] ?? 0),
            ])
            ->first();
    }

    /**
     * Items that can honestly be offered as a win right now.
     *
     * A win has to come from a row that is working today, not one that is easy
     * on the thirty-day average. His ሰ family is an 89% row; he opened a real
     * sitting by missing ሰ ሰ ሱ, and fifty attempts later the engine handed him
     * ሶ from that same row to finish on — a letter he had been coached through
     * minutes earlier, from the family he had already failed.
     *
     * This is one rule applied everywhere a win is chosen. It was written once
     * for the closing row and left out of the other three places, which is why
     * it did not hold.
     */
    private function winnable(Collection $eligible, Collection $stats, array $settings): Collection
    {
        $abandonAfter = (int) $settings['focus']['abandon_after_misses'];

        $struggling = $stats
            ->filter(fn ($i) => $i['level'] !== null)
            ->groupBy('level')
            ->filter(fn ($rows) => $rows->sum('session_misses') >= $abandonAfter)
            ->keys()
            ->map(fn ($level) => (int) $level)
            ->all();

        return $struggling === []
            ? $eligible
            : $eligible->reject(fn ($i) => in_array($i['level'], $struggling, true));
    }

    /**
     * A win, to break a run of misses or to close the sitting.
     *
     * Taken from a level he has already finished today where possible: he has
     * just had them right, so they are the surest thing in the category, and
     * using them does not eat into a level still to come.
     */
    private function breather(
        Collection $eligible,
        ?int $current,
        array $playlist,
        Collection $stats,
        array $settings,
        ?int $lastLevel = null,
        array $soundOf = [],
    ): ?array {
        $eligible = $this->winnable($eligible, $stats, $settings);

        // Also step away from the row he has just been missing in, even if it
        // has not yet earned the abandon threshold. Two misses is enough to
        // know that row is not where a win is going to come from — and step
        // away from the same sound too, since ሐ after ሀ is the same /h/ in a
        // different row and no kind of change.
        if ($lastLevel !== null) {
            $sound = $soundOf[$lastLevel] ?? null;

            $eligible = $eligible->reject(fn ($i) => $i['level'] === $lastLevel
                || ($sound !== null && ($soundOf[$i['level']] ?? null) === $sound));
        }

        // Level places only: a mixed place has no row of its own to come back
        // to, and its letters are reachable through the easy pool anyway.
        $done = collect($playlist)
            ->where('type', 'level')
            ->pluck('level')
            ->filter(fn ($level) => $level !== $current
                && $level !== $lastLevel
                && $stats->where('level', $level)->sum('session_attempts') > 0)
            ->values()
            ->all();

        $fromFinished = $eligible->whereIn('level', $done);

        if ($fromFinished->isNotEmpty()) {
            return $this->rowOpener($fromFinished, $stats, $soundOf);
        }

        return $this->winFrom($eligible, $current ?? -1, $settings, null, $stats, $soundOf);
    }

    /**
     * Every level with its accuracy and when it was last worked.
     *
     * @return Collection<int, array{level:int, accuracy:?float, last_worked:int}>
     */
    private function levelSummary(Collection $stats, array $settings): Collection
    {
        return $stats
            ->filter(fn ($i) => $i['level'] !== null)
            ->groupBy('level')
            ->map(function ($items, $level) {
                $known = $items->filter(fn ($i) => $i['accuracy'] !== null);

                return [
                    'level' => (int) $level,
                    'accuracy' => $known->isEmpty() ? null : $known->avg('accuracy'),
                    // Blind to the sitting in progress, so the playlist stays
                    // the same from the first item to the last.
                    'last_worked' => $items->max(fn ($i) => $i['last_worked']?->timestamp) ?? 0,
                ];
            })
            ->values();
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
    private function finishIfDone(
        Collection $session,
        array $settings,
        int $position,
        int $total,
        bool $lastWasMiss,
        bool $rowUnfinished = false,
        bool $closeDone = false,
    ): ?array {
        if ($session->isEmpty()) {
            return null;
        }

        $maxMinutes = (int) $settings['session']['max_minutes'];
        $elapsed = abs($session->first()->created_at->diffInMinutes(now()));
        $overrunBy = $position - $total;
        $ceiling = (int) $settings['session']['max_overrun'];

        $atCap = $position > $total || $closeDone;
        $outOfTime = $elapsed >= $maxMinutes;

        // Past the cap with a row still open: finish the row. Fifty-six
        // attempts with the family complete beats fifty with it abandoned
        // halfway, which is what "a level at a time" has to mean.
        if ($rowUnfinished && $overrunBy <= $ceiling) {
            return null;
        }

        if (!$atCap && !$outOfTime) {
            return null;
        }

        // Never end on a miss, bounded by the same ceiling as everything else.
        //
        // This used to have a small budget of its own, counted from the cap —
        // which the row-finishing overrun had already spent by the time it was
        // needed. A real sitting reached 55 of a target 50, so the budget of 3
        // was long gone, and it ended him on ቦ✗ after five misses in a row.
        if ($lastWasMiss && $overrunBy <= $ceiling) {
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
    private function winFrom(
        Collection $eligible,
        int $focus,
        array $settings,
        ?Collection $winnableAgainst = null,
        ?Collection $stats = null,
        array $soundOf = [],
    ): ?array {
        if ($winnableAgainst) {
            $eligible = $this->winnable($eligible, $winnableAgainst, $settings);
        }

        $elsewhere = $eligible->where('level', '!=', $focus);

        if ($elsewhere->isEmpty()) {
            return null;
        }

        // Strong ROWS, not strong letters. Filtering letter by letter leaves
        // only the handful he never misses, and opening a row from those hands
        // him ላ out of ለ ሉ ሊ ላ ሌ ል ሎ — the strongest letter of a middling row,
        // presented as the start of it. A win should be the beginning of a row
        // he has, so the row is what has to qualify.
        $strongRows = $elsewhere
            ->groupBy('level')
            ->filter(function ($rows) use ($settings) {
                $known = $rows->filter(fn ($i) => $i['accuracy'] !== null);

                return $known->isNotEmpty() && $known->avg('accuracy') >= $settings['bands']['support_min'];
            })
            ->flatten(1);

        if ($strongRows->isNotEmpty()) {
            return $this->rowOpener($strongRows, $stats, $soundOf);
        }

        // No row is strong enough to open: fall back to the surest letters
        // going, which is better than offering a row he cannot start.
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
     * A win from a row, taken at the row's own beginning.
     *
     * Pick the strongest row available, then its first letter — not its best
     * letter. Sorting straight by accuracy handed him ሆ out of ሀ ሁ ሂ ሃ ሄ ህ ሆ,
     * because ሆ is the one he never misses. That is the end of the row offered
     * as though it were the start of it, and it also spends the letter, so
     * when the row's own turn comes it opens on ሁ with ሆ already gone.
     */
    private function rowOpener(Collection $candidates, Collection $stats = null, array $soundOf = []): ?array
    {
        if ($candidates->isEmpty()) {
            return null;
        }

        // How much of the sitting each sound has already had. Without this the
        // wins all come from one row: ሀ opens the session, and then ሀ ሂ ሃ ሄ ህ
        // supply every breather after it, so he spends a third of the sitting
        // on /h/ however many other rows he has.
        $soundUse = [];

        if ($stats && $soundOf !== []) {
            foreach ($stats as $item) {
                $sound = $soundOf[$item['level']] ?? null;

                if ($sound !== null) {
                    $soundUse[$sound] = ($soundUse[$sound] ?? 0) + $item['session_attempts'];
                }
            }
        }

        $byLevel = $candidates->groupBy('level');

        $best = $byLevel
            ->map(fn ($rows, $level) => [
                'level' => $level,
                // Least-used sound first, then untouched rows before part-used
                // ones, then the strongest.
                'sound_used' => $soundUse[$soundOf[(int) $level] ?? -1] ?? 0,
                'used' => $rows->sum('session_attempts'),
                'accuracy' => $rows->filter(fn ($i) => $i['accuracy'] !== null)->avg('accuracy') ?? 0,
            ])
            ->sortBy(fn ($l) => [$l['sound_used'], $l['used'], -$l['accuracy']])
            ->first();

        return $byLevel[$best['level']]
            ->sortBy(fn ($i) => [$i['session_attempts'], $i['order'] ?? PHP_INT_MAX])
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
        array $soundOf = [],
    ): Collection {
        $setAsideAfter = (int) $settings['misses']['set_aside_after'];

        // One row per sound for the whole sitting, not just for the playlist.
        // ሀ, ሐ and ኀ are the same seven sounds written three ways, so once one
        // of them has been used the other two are the same practice over
        // again. Applying this only when the playlist was built let the wins,
        // the breathers and the close pull the other two rows in anyway — a
        // sitting still spent a third of itself on /h/.
        $soundOwner = [];

        foreach ($stats as $item) {
            $sound = $soundOf[$item['level']] ?? null;

            if ($sound !== null && $item['session_attempts'] > 0 && !isset($soundOwner[$sound])) {
                $soundOwner[$sound] = $item['level'];
            }
        }

        $maxRepeats = $relaxRepeats
            ? PHP_INT_MAX
            : (int) $settings['spacing']['max_repeats_per_session'];

        // Resolved once: reading it inside the filter would load the word
        // again for every item in the category.
        $lastWord = $last?->word?->word;

        return $stats->filter(function (array $item) use (
            $last, $lastWord, $lastWasMiss, $userId, $setAsideAfter, $maxRepeats, $soundOf, $soundOwner
        ) {
            // Another row of a sound already being practised today.
            $sound = $soundOf[$item['level']] ?? null;

            if ($sound !== null && isset($soundOwner[$sound]) && $soundOwner[$sound] !== $item['level']) {
                return false;
            }

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
