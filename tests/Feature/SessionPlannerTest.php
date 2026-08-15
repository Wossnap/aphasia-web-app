<?php

namespace Tests\Feature;

use App\Models\AmharicWord;
use App\Models\Category;
use App\Models\SpeechAttempt;
use App\Models\User;
use App\Services\Practice\SessionPlanner;
use App\Services\Practice\WordFamily;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The rules that stop a practice session becoming a wall.
 *
 * The old app re-showed a missed item 74% of the time and a cleared one 6%, so
 * an item he could not win became a loop he could not leave: 450 runs of five
 * or more consecutive misses in the log, and one of 61 that ran from one
 * afternoon into the next morning.
 */
class SessionPlannerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Carbon::setTestNow('2026-08-20 09:00:00');
        $this->user = User::factory()->create();
        $this->category = Category::create(['name' => 'Test', 'slug' => 'test']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function word(string $word, int $level = 1, ?int $order = null): AmharicWord
    {
        $item = AmharicWord::create(['word' => $word, 'transliterations' => [$word], 'order' => $order]);
        $this->category->words()->attach($item->id, ['level' => $level]);

        return $item;
    }

    /**
     * Run a sitting and record what was served, so a test can assert on the
     * shape of the whole thing rather than one call at a time.
     *
     * @return array<int, array{word:string, level:?int, slot:string}>
     */
    private function runSitting(int $turns, bool $allCorrect = true): array
    {
        $served = [];

        foreach (range(1, $turns) as $_) {
            $plan = $this->next();

            if ($plan['done']) {
                break;
            }

            $served[] = [
                'word' => $plan['item']['word'],
                'level' => $plan['item']['level'],
                'slot' => $plan['slot'],
            ];

            $this->attemptNow($plan['item'], correct: $allCorrect, secondsAgo: 0);
            Carbon::setTestNow(Carbon::now()->addSeconds(20));
        }

        return $served;
    }

    /** Give an item a history, so its accuracy is known rather than null. */
    private function history(AmharicWord $word, int $correct, int $wrong, string $at = '2026-08-01 09:00:00'): void
    {
        foreach (range(1, $correct + $wrong) as $n) {
            $attempt = new SpeechAttempt([
                'user_id' => $this->user->id,
                'amharic_word_id' => $word->id,
                'transcription' => 'x',
                'is_correct' => $n <= $correct,
            ]);
            $attempt->created_at = Carbon::parse($at)->addSeconds($n);
            $attempt->updated_at = $attempt->created_at;
            $attempt->save();
        }
    }

    /** An attempt inside the sitting happening now. */
    private function attemptNow(AmharicWord|array|int $word, bool $correct, int $secondsAgo = 10): void
    {
        $wordId = match (true) {
            $word instanceof AmharicWord => $word->id,
            is_array($word) => $word['id'],
            default => $word,
        };

        $attempt = new SpeechAttempt([
            'user_id' => $this->user->id,
            'amharic_word_id' => $wordId,
            'transcription' => 'x',
            'is_correct' => $correct,
        ]);
        $attempt->created_at = Carbon::now()->subSeconds($secondsAgo);
        $attempt->updated_at = $attempt->created_at;
        $attempt->save();
    }

    private function next(): array
    {
        return app(SessionPlanner::class)->next($this->user->id, $this->category->fresh());
    }

    public function test_a_family_is_read_off_the_character_not_a_table(): void
    {
        $families = app(WordFamily::class);

        $this->assertSame($families->of('ጠ'), $families->of('ጡ'), 'ጠ and ጡ are one consonant');
        $this->assertNotSame($families->of('ጠ'), $families->of('ሰ'));
        $this->assertNull($families->of('ሰው'), 'a word has no family');
        $this->assertNull($families->of('ዳቦ'));
        $this->assertFalse($families->areSiblings('ሰው', 'ዳቦ'), 'two words are never siblings');
    }

    /**
     * The wall itself: one retry is ordinary practice, an unbounded loop is
     * what produced the 61-miss run.
     */
    public function test_one_retry_is_allowed_then_the_engine_moves_on(): void
    {
        $missed = $this->word('ጠ');
        $this->history($missed, 6, 4);
        $other = $this->word('ሰ');
        $this->history($other, 8, 2);

        $this->attemptNow($missed, correct: false, secondsAgo: 20);

        $retry = $this->next();
        $this->assertSame($missed->id, $retry['item']['id'], 'the first miss earns one more go');
        $this->assertTrue($retry['item']['retry']);

        // He misses the retry too.
        $this->attemptNow($missed, correct: false, secondsAgo: 10);

        $moveOn = $this->next();
        $this->assertNotSame($missed->id, $moveOn['item']['id'], 'the second miss moves on');
    }

    public function test_after_a_miss_it_does_not_serve_the_same_consonant_family(): void
    {
        $missed = $this->word('ጠ');
        $this->history($missed, 6, 4);

        // Every sibling looks attractive on accuracy, so only the family rule
        // can keep them out.
        foreach (['ጡ', 'ጢ', 'ጣ', 'ጤ', 'ጥ', 'ጦ'] as $sibling) {
            $this->history($this->word($sibling), 9, 1);
        }

        $escape = $this->word('ሰ');
        $this->history($escape, 7, 3);

        // Two misses so the retry is spent and it has to choose someone else.
        $this->attemptNow($missed, correct: false, secondsAgo: 30);
        $this->attemptNow($missed, correct: false, secondsAgo: 20);

        $plan = $this->next();

        $this->assertSame('ሰ', $plan['item']['word'], 'it must leave the ጠ family entirely');
    }

    public function test_after_a_miss_it_avoids_items_his_own_log_says_get_confused(): void
    {
        $missed = $this->word('ቃ');
        $this->history($missed, 6, 4);

        // Different family, but the recogniser keeps returning ካ for ቃ.
        $confused = $this->word('ካ');
        $this->history($confused, 9, 1);

        $safe = $this->word('ሰ');
        $this->history($safe, 9, 1);

        foreach (range(1, 6) as $n) {
            $attempt = new SpeechAttempt([
                'user_id' => $this->user->id,
                'amharic_word_id' => $missed->id,
                'transcription' => 'ካ',
                'is_correct' => false,
            ]);
            $attempt->created_at = Carbon::parse('2026-08-02 09:00:00')->addSeconds($n);
            $attempt->updated_at = $attempt->created_at;
            $attempt->save();
        }

        Cache::flush();

        $this->attemptNow($missed, correct: false, secondsAgo: 30);
        $this->attemptNow($missed, correct: false, secondsAgo: 20);

        $plan = $this->next();

        $this->assertSame('ሰ', $plan['item']['word'], 'ካ is a different family but the same wall');
    }

    /**
     * Moving on after two misses fixes the loop on one item but not the run
     * that walks item to item. In simulation against his real accuracy that
     * still produced runs of twelve.
     */
    public function test_a_run_of_misses_across_items_forces_a_win(): void
    {
        $hard = collect(['ጠ', 'ሸ', 'ኘ'])->map(function ($w) {
            $item = $this->word($w);
            $this->history($item, 3, 7); // 30% — eligible but hard
            return $item;
        });

        $sure = $this->word('ሰ');
        $this->history($sure, 19, 1); // 95%

        $this->attemptNow($hard[0], correct: false, secondsAgo: 40);
        $this->attemptNow($hard[1], correct: false, secondsAgo: 30);

        $plan = $this->next();

        $this->assertSame($sure->id, $plan['item']['id'], 'two misses in a row and he needs a win');
        $this->assertSame('close', $plan['slot']);
    }

    /**
     * He goes at the hard levels on purpose. An engine that withheld them
     * would be removing the thing he is actually trying to do — so hard items
     * stay in, and the miss rules are what make them survivable.
     */
    public function test_hard_items_are_still_served_rather_than_withheld(): void
    {
        $hard = $this->word('ጠ', level: 5);
        $this->history($hard, 1, 19); // 5% — exactly what he chooses to work on

        foreach (['ሰ', 'ሱ', 'ሲ'] as $ok) {
            $this->history($this->word($ok, level: 9), 8, 2);
        }

        $seen = [];
        foreach (range(1, 12) as $_) {
            $plan = $this->next();

            if ($plan['done']) {
                break;
            }

            $seen[] = $plan['item']['id'];
            $this->attemptNow($plan['item'], correct: true, secondsAgo: 0);
            Carbon::setTestNow(Carbon::now()->addSeconds(20));
        }

        $this->assertContains($hard->id, $seen, 'the hard level is the work, not something to avoid');
    }

    public function test_an_item_never_seen_before_is_unknown_rather_than_bad(): void
    {
        // A short sitting, so the run reaches the working part rather than
        // spending every turn on warm-ups.
        config(['practice.session.max_attempts' => 10]);

        // No history at all. It must not be read as 0% and withheld — never
        // attempted is "not started", which is not the same as "hard".
        $fresh = $this->word('ጠ');

        foreach (['ሰ', 'ለ', 'መ'] as $known) {
            $this->history($this->word($known), 9, 1);
        }

        $seen = [];
        foreach (range(1, 8) as $_) {
            $plan = $this->next();

            if ($plan['done']) {
                break;
            }

            $seen[] = $plan['item']['id'];
            $this->attemptNow($plan['item'], correct: true, secondsAgo: 0);
            Carbon::setTestNow(Carbon::now()->addSeconds(20));
        }

        $this->assertContains($fresh->id, $seen, 'a new item must still get shown');
    }

    public function test_the_session_ends_at_the_cap_and_never_on_a_miss(): void
    {
        config(['practice.session.max_attempts' => 4]);

        $sure = $this->word('ሰ');
        $this->history($sure, 19, 1);
        $other = $this->word('ለ');
        $this->history($other, 18, 2);

        foreach (range(1, 4) as $n) {
            $this->attemptNow($n % 2 ? $sure : $other, correct: true, secondsAgo: 40 - $n * 5);
        }

        $plan = $this->next();

        $this->assertTrue($plan['done']);
        $this->assertSame('cap', $plan['reason']);
    }

    public function test_past_the_cap_it_keeps_going_only_to_land_a_win(): void
    {
        config(['practice.session.max_attempts' => 3]);

        $sure = $this->word('ሰ');
        $this->history($sure, 19, 1);
        $hard = $this->word('ጠ');
        $this->history($hard, 4, 6);

        $this->attemptNow($sure, correct: true, secondsAgo: 40);
        $this->attemptNow($sure, correct: true, secondsAgo: 30);
        $this->attemptNow($hard, correct: false, secondsAgo: 20);

        $plan = $this->next();

        $this->assertFalse($plan['done'], 'it must not end him on a failure');
        $this->assertSame('close', $plan['slot']);
        $this->assertSame($sure->id, $plan['item']['id']);
    }

    public function test_a_sitting_opens_on_something_he_can_do(): void
    {
        $sure = $this->word('ሰ');
        $this->history($sure, 19, 1);
        $hard = $this->word('ጠ');
        $this->history($hard, 4, 6);

        $plan = $this->next();

        $this->assertSame('warm_up', $plan['slot']);
        $this->assertSame($sure->id, $plan['item']['id']);
    }

    /**
     * What "level by level" means: a playlist of whole levels, each finished
     * in its own order before the next begins —
     *
     *     ሀ ሁ ሂ    then    ለ ሉ ሊ
     *
     * and not one letter taken from each of three different families, which
     * is what the earlier version did.
     */
    public function test_by_level_it_finishes_a_level_before_starting_the_next(): void
    {
        $this->category->update(['session_mode' => Category::SESSION_BY_LEVEL]);
        config(['practice.session.max_attempts' => 12]);

        foreach ([['ሀ', 1], ['ሁ', 2], ['ሂ', 3]] as [$letter, $order]) {
            $this->history($this->word($letter, level: 1, order: $order), 19, 1);
        }

        // Middling rather than easy, so the playlist's opening "easy" place
        // can only be level 1 and the assertion is about the walk rather than
        // about which of two equals came up first.
        foreach ([['ለ', 4], ['ሉ', 5], ['ሊ', 6]] as [$letter, $order]) {
            $this->history($this->word($letter, level: 2, order: $order), 11, 9); // 55%
        }

        $served = $this->runSitting(6);

        $this->assertSame(
            ['ሀ', 'ሁ', 'ሂ', 'ለ', 'ሉ', 'ሊ'],
            array_column($served, 'word'),
            'one whole level, in order, then the next',
        );
    }

    /**
     * Inside a level the sibling rule is deliberately suspended: ገ ጉ ጊ in
     * sequence is what level by level means, and they are all one consonant.
     * Stepping away sound by sound is not what bounds a bad run here.
     */
    public function test_by_level_a_miss_carries_on_through_the_level(): void
    {
        $this->category->update(['session_mode' => Category::SESSION_BY_LEVEL]);
        config(['practice.session.max_attempts' => 12]);

        $missed = $this->word('ጠ', level: 5, order: 1);
        $this->history($missed, 6, 4);

        foreach ([['ጡ', 2], ['ጢ', 3], ['ጣ', 4]] as [$sibling, $order]) {
            $this->history($this->word($sibling, level: 5, order: $order), 9, 1);
        }

        $this->attemptNow($missed, correct: false, secondsAgo: 30);
        $this->attemptNow($missed, correct: false, secondsAgo: 20);

        $plan = $this->next();

        $this->assertSame('ጡ', $plan['item']['word'], 'the row carries on rather than jumping away');
    }

    /**
     * What does bound a bad run in level mode: the level is cut short rather
     * than pushed through to the end of the row, and the playlist follows it
     * with something easier.
     */
    public function test_by_level_a_level_going_badly_is_abandoned(): void
    {
        $this->category->update(['session_mode' => Category::SESSION_BY_LEVEL]);
        config(['practice.session.max_attempts' => 20, 'practice.focus.abandon_after_misses' => 4]);

        foreach ([['ጠ', 1], ['ጡ', 2], ['ጢ', 3], ['ጣ', 4]] as [$letter, $order]) {
            $this->history($this->word($letter, level: 5, order: $order), 8, 2);
        }

        foreach ([['ሰ', 5], ['ሱ', 6]] as [$letter, $order]) {
            $this->history($this->word($letter, level: 9, order: $order), 19, 1);
        }

        $seconds = 60;
        foreach (['ጠ', 'ጠ', 'ጡ', 'ጡ'] as $letter) {
            $this->attemptNow(AmharicWord::where('word', $letter)->first(), correct: false, secondsAgo: $seconds);
            $seconds -= 5;
        }

        $plan = $this->next();

        $this->assertNotSame(5, $plan['item']['level'], 'a level going badly is cut short');
    }

    /**
     * The playlist opens on a level he is good at, so the sitting starts on
     * something that goes well before the work begins.
     */
    public function test_by_level_it_opens_on_an_easy_level(): void
    {
        $this->category->update(['session_mode' => Category::SESSION_BY_LEVEL]);
        config(['practice.session.max_attempts' => 12]);

        foreach ([['ጠ', 1], ['ጡ', 2], ['ጢ', 3]] as [$letter, $order]) {
            $this->history($this->word($letter, level: 5, order: $order), 2, 18); // 10%
        }

        foreach ([['ሰ', 4], ['ሱ', 5], ['ሲ', 6]] as [$letter, $order]) {
            $this->history($this->word($letter, level: 9, order: $order), 19, 1); // 95%
        }

        $served = $this->runSitting(3);

        $this->assertSame([9, 9, 9], array_column($served, 'level'), 'open on the easy level');
        $this->assertSame(['ሰ', 'ሱ', 'ሲ'], array_column($served, 'word'));
    }

    /**
     * And the hard level is the next thing served, not something avoided. He
     * goes at the hard levels on purpose.
     */
    public function test_by_level_the_hard_level_comes_after_the_opener(): void
    {
        $this->category->update(['session_mode' => Category::SESSION_BY_LEVEL]);
        config(['practice.session.max_attempts' => 12]);

        foreach ([['ጠ', 1], ['ጡ', 2], ['ጢ', 3]] as [$letter, $order]) {
            $this->history($this->word($letter, level: 5, order: $order), 2, 18); // 10%
        }

        foreach ([['ሰ', 4], ['ሱ', 5], ['ሲ', 6]] as [$letter, $order]) {
            $this->history($this->word($letter, level: 9, order: $order), 19, 1);
        }

        $served = $this->runSitting(6);

        $this->assertSame([9, 9, 9, 5, 5, 5], array_column($served, 'level'));
        $this->assertSame(['ጠ', 'ጡ', 'ጢ'], array_slice(array_column($served, 'word'), 3));
    }

    /**
     * Inside a level, practice runs in the category's own sequence — the
     * order the alphabet is taught and recited in — rather than by score.
     */
    public function test_by_level_it_runs_in_the_categorys_own_order(): void
    {
        $this->category->update(['session_mode' => Category::SESSION_BY_LEVEL]);
        config(['practice.session.max_attempts' => 10]);

        // Deliberately created out of order, and with the later letters
        // stronger, so only the order column can produce ሀ first.
        $letters = [];
        foreach ([['ሆ', 7, 19], ['ሁ', 2, 15], ['ሀ', 1, 8], ['ሂ', 3, 17]] as [$letter, $order, $correct]) {
            $item = AmharicWord::create(['word' => $letter, 'transliterations' => [$letter], 'order' => $order]);
            $this->category->words()->attach($item->id, ['level' => 1]);
            $this->history($item, $correct, 20 - $correct);
            $letters[$letter] = $item;
        }

        // Wins to open on, from another level.
        foreach (['ሰ', 'ሱ'] as $support) {
            $this->history($this->word($support, level: 9), 19, 1);
        }

        $served = [];
        foreach (range(1, 8) as $_) {
            $plan = $this->next();

            if ($plan['done']) {
                break;
            }

            if ($plan['slot'] === 'focus') {
                $served[] = $plan['item']['word'];
            }

            $this->attemptNow($plan['item'], correct: true, secondsAgo: 0);
            Carbon::setTestNow(Carbon::now()->addSeconds(20));
        }

        $this->assertSame(['ሀ', 'ሁ', 'ሂ'], array_slice($served, 0, 3), 'ሀ ሁ ሂ, not strongest first');
    }

    /**
     * The rotation, which is what stops it serving the same thing every day:
     * the focus goes to whatever has been left alone longest.
     */
    public function test_the_focus_rotates_to_the_level_left_alone_longest(): void
    {
        $this->category->update(['session_mode' => Category::SESSION_BY_LEVEL]);

        // Worked yesterday.
        foreach (['ሰ', 'ሱ'] as $recent) {
            $this->history($this->word($recent, level: 9), 5, 5, at: '2026-08-19 09:00:00');
        }

        // Not touched in a fortnight.
        $stale = [];
        foreach (['ጠ', 'ጡ'] as $old) {
            $item = $this->word($old, level: 5);
            $stale[] = $item->id;
            $this->history($item, 5, 5, at: '2026-08-05 09:00:00');
        }

        $focus = app(SessionPlanner::class)->next($this->user->id, $this->category->fresh())['focus_level'];

        $this->assertSame(5, $focus, 'the overdue level comes up, not the one just worked');
    }

    /**
     * The setting is per category because what a level means is per category,
     * and only a person can say which reading suits.
     */
    public function test_the_mode_is_a_per_category_setting(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assertSame(Category::SESSION_BY_WORD, $this->category->session_mode, 'word by word by default');

        $this->actingAs($admin)
            ->put(route('admin.categories.update', $this->category), [
                'name' => 'Test',
                'session_mode' => Category::SESSION_BY_LEVEL,
            ])
            ->assertRedirect();

        $this->assertTrue($this->category->fresh()->worksByLevel());
    }

    private function levelOf(int $wordId): ?int
    {
        return $this->category->words()->where('amharic_words.id', $wordId)->first()?->pivot->level;
    }

    /**
     * The setting that decides whether easy levels are walked as rows or
     * spent as loose wins. It exists because the research does not settle it:
     * blocked and random practice come out equal for learning something, and
     * random slightly ahead for still having it months later, in a single
     * 10-person study. His own data can answer it better than I can.
     */
    public function test_easy_levels_can_be_mixed_in_rather_than_walked_as_rows(): void
    {
        $this->category->update([
            'session_mode' => Category::SESSION_BY_LEVEL,
            'easy_level_mode' => Category::EASY_AS_MIXED,
        ]);
        config(['practice.session.max_attempts' => 12, 'practice.mixed_win_run' => 4]);

        // Two easy families. Walked as rows they would come out ሰ ሱ ሲ ሶ;
        // mixed, the run should cross both.
        foreach ([['ሰ', 1], ['ሱ', 2], ['ሲ', 3], ['ሶ', 4]] as [$letter, $order]) {
            $this->history($this->word($letter, level: 9, order: $order), 19, 1);
        }

        foreach ([['ለ', 5], ['ሉ', 6], ['ሊ', 7], ['ሎ', 8]] as [$letter, $order]) {
            $this->history($this->word($letter, level: 2, order: $order), 18, 2);
        }

        foreach ([['ጠ', 9], ['ጡ', 10]] as [$letter, $order]) {
            $this->history($this->word($letter, level: 5, order: $order), 2, 18);
        }

        $served = $this->runSitting(4);
        $levels = array_column($served, 'level');

        $this->assertCount(4, $served);
        $this->assertGreaterThan(
            1,
            count(array_unique($levels)),
            'a mixed run should cross more than one easy family',
        );
        $this->assertNotContains(5, $levels, 'the hard row comes after the wins, not during them');
    }

    public function test_whole_rows_is_the_default_for_easy_levels(): void
    {
        $this->assertSame(Category::EASY_AS_ROWS, $this->category->easy_level_mode);
        $this->assertFalse($this->category->mixesEasyLevels());

        $this->category->update(['session_mode' => Category::SESSION_BY_LEVEL]);
        $this->assertFalse($this->category->fresh()->mixesEasyLevels(), 'level mode alone does not mix');

        $this->category->update(['easy_level_mode' => Category::EASY_AS_MIXED]);
        $this->assertTrue($this->category->fresh()->mixesEasyLevels());
    }

    /**
     * The playlist ends on an easy level, but only for someone who reaches
     * the end of it — and the cap cuts long before that. His first real
     * sitting under this engine ran out in the middle of the ነ family and
     * finished there: on a win, but on one of his hardest rows.
     */
    public function test_the_last_stretch_is_held_back_for_an_easy_close(): void
    {
        $this->category->update(['session_mode' => Category::SESSION_BY_LEVEL]);
        config(['practice.session.max_attempts' => 8, 'practice.session.closing_reserve' => 3]);

        foreach ([['ሰ', 1], ['ሱ', 2], ['ሲ', 3]] as [$letter, $order]) {
            $this->history($this->word($letter, level: 9, order: $order), 19, 1); // easy
        }

        foreach ([['ገ', 4], ['ጉ', 5], ['ጊ', 6], ['ጋ', 7], ['ጌ', 8]] as [$letter, $order]) {
            $this->history($this->word($letter, level: 27, order: $order), 2, 18); // hard
        }

        $served = $this->runSitting(8);
        $closing = array_slice($served, -3);

        foreach ($closing as $item) {
            $this->assertSame(9, $item['level'], 'the sitting must finish on ground he is sure of');
            $this->assertSame('close', $item['slot']);
        }
    }

    public function test_the_endpoint_answers_with_a_plan(): void
    {
        $word = $this->word('ሰ');
        $this->history($word, 9, 1);

        $this->actingAs($this->user)
            ->getJson(route('api.practice.next', ['category_id' => $this->category->id]))
            ->assertOk()
            ->assertJsonStructure(['done', 'item' => ['id', 'word'], 'slot', 'position', 'total']);
    }

    public function test_the_engine_needs_no_knowledge_of_the_category(): void
    {
        // A word category: nothing here has a consonant family, and the engine
        // has never been told that.
        $words = Category::create(['name' => 'Daily words', 'slug' => 'daily-words']);

        foreach (['ሰው' => [8, 2], 'ዳቦ' => [7, 3], 'ምሳ' => [9, 1]] as $text => [$ok, $bad]) {
            $item = AmharicWord::create(['word' => $text, 'transliterations' => [$text]]);
            $words->words()->attach($item->id, ['level' => 1]);
            $this->history($item, $ok, $bad);
        }

        $plan = app(SessionPlanner::class)->next($this->user->id, $words);

        $this->assertFalse($plan['done']);
        $this->assertContains($plan['item']['word'], ['ሰው', 'ዳቦ', 'ምሳ']);
    }

    /**
     * ሰው missing does not make ዳቦ off-limits: unrelated words share no family,
     * so the rule that protects the fidel families must not strand a word
     * category with nothing to serve.
     */
    public function test_a_missed_word_does_not_block_the_other_words(): void
    {
        $words = Category::create(['name' => 'Daily words', 'slug' => 'daily']);

        $missed = AmharicWord::create(['word' => 'ሰው', 'transliterations' => ['ሰው']]);
        $other = AmharicWord::create(['word' => 'ዳቦ', 'transliterations' => ['ዳቦ']]);
        $words->words()->attach($missed->id, ['level' => 1]);
        $words->words()->attach($other->id, ['level' => 1]);
        $this->history($missed, 6, 4);
        $this->history($other, 8, 2);

        $this->attemptNow($missed, correct: false, secondsAgo: 30);
        $this->attemptNow($missed, correct: false, secondsAgo: 20);

        $plan = app(SessionPlanner::class)->next($this->user->id, $words);

        $this->assertSame($other->id, $plan['item']['id']);
    }
}
