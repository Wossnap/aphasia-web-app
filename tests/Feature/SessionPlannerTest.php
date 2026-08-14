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

    private function word(string $word, int $level = 1): AmharicWord
    {
        $item = AmharicWord::create(['word' => $word, 'transliterations' => [$word]]);
        $this->category->words()->attach($item->id, ['level' => $level]);

        return $item;
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
     * Working a level at a time. In the fidel category a level is one
     * consonant family, so this is what makes a sitting run ሀ ሁ ሂ ሃ … the way
     * he has always practised, instead of hopping between families.
     */
    /**
     * Working a level at a time. In the fidel category a level is one
     * consonant family, so this is what makes a sitting run ሀ ሁ ሂ ሃ … the way
     * he has always practised, instead of hopping between families.
     */
    public function test_by_level_it_stays_in_a_level_while_it_is_going_well(): void
    {
        $this->category->update(['session_mode' => Category::SESSION_BY_LEVEL]);
        config(['practice.session.max_attempts' => 10]);

        foreach (['ሀ', 'ሁ', 'ሂ', 'ሃ', 'ሄ'] as $letter) {
            $this->history($this->word($letter, level: 1), 8, 2);
        }

        // A stronger level, so the wins have somewhere to come from and the
        // focus is not simply the only thing available.
        foreach (['ለ', 'ሉ', 'ሊ'] as $letter) {
            $this->history($this->word($letter, level: 2), 19, 1);
        }

        $focusLevels = [];
        foreach (range(1, 8) as $_) {
            $plan = $this->next();

            if ($plan['done']) {
                break;
            }

            if ($plan['slot'] === 'focus') {
                $focusLevels[] = $this->levelOf($plan['item']['id']);
            }

            $this->attemptNow($plan['item'], correct: true, secondsAgo: 0);
            Carbon::setTestNow(Carbon::now()->addSeconds(20));
        }

        $this->assertNotEmpty($focusLevels);
        $this->assertSame([1], array_values(array_unique($focusLevels)),
            'a good run stays inside the one level it is working');
    }

    public function test_by_level_a_miss_still_moves_him_out_of_the_level(): void
    {
        $this->category->update(['session_mode' => Category::SESSION_BY_LEVEL]);

        $missed = $this->word('ጠ', level: 5);
        $this->history($missed, 6, 4);

        foreach (['ጡ', 'ጢ', 'ጣ'] as $sibling) {
            $this->history($this->word($sibling, level: 5), 9, 1);
        }

        $elsewhere = $this->word('ሰ', level: 9);
        $this->history($elsewhere, 8, 2);

        $this->attemptNow($missed, correct: false, secondsAgo: 30);
        $this->attemptNow($missed, correct: false, secondsAgo: 20);

        $plan = $this->next();

        $this->assertSame($elsewhere->id, $plan['item']['id'], 'a miss must leave the family');
    }

    public function test_by_level_it_opens_on_a_win_from_outside_the_focus(): void
    {
        $this->category->update(['session_mode' => Category::SESSION_BY_LEVEL]);

        foreach (['ጠ', 'ጡ', 'ጢ'] as $weak) {
            $this->history($this->word($weak, level: 5), 4, 6); // 40%
        }

        $strongest = [];
        foreach (['ሰ', 'ሱ', 'ሲ'] as $strong) {
            $item = $this->word($strong, level: 9);
            $strongest[] = $item->id;
            $this->history($item, 19, 1); // 95%
        }

        $plan = $this->next();

        $this->assertSame('warm_up', $plan['slot']);
        $this->assertContains($plan['item']['id'], $strongest, 'a sitting opens on something he has');
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
