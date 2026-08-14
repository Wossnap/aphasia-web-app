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

    public function test_items_he_cannot_do_alone_leave_solo_practice(): void
    {
        $quarantined = $this->word('ጠ');
        $this->history($quarantined, 1, 19); // 5% — this is where the walls live

        // Enough usable items that the sitting does not simply run out, which
        // would pass the assertion for the wrong reason.
        foreach (['ሰ', 'ለ', 'መ', 'ረ', 'በ', 'ተ'] as $ok) {
            $this->history($this->word($ok), 8, 2);
        }

        foreach (range(1, 10) as $_) {
            $plan = $this->next();

            $this->assertFalse($plan['done'], 'the sitting should still have items to give');
            $this->assertNotSame($quarantined->id, $plan['item']['id']);

            $this->attemptNow($plan['item'], correct: true, secondsAgo: 0);
            Carbon::setTestNow(Carbon::now()->addSeconds(20));
        }
    }

    public function test_an_item_never_seen_before_is_unknown_rather_than_bad(): void
    {
        // No history at all. It must not be read as 0% and quarantined —
        // never attempted is "not started", which is not the same as "hard".
        $fresh = $this->word('ጠ');

        foreach (['ሰ', 'ለ', 'መ'] as $known) {
            $this->history($this->word($known), 9, 1);
        }

        $seen = [];
        foreach (range(1, 6) as $_) {
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
