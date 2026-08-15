<?php

namespace Tests\Feature;

use App\Models\AmharicWord;
use App\Models\Category;
use App\Models\SpeechAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The admin list of items that need a person sitting with him. Assisted
 * practice lives here rather than in his app on purpose — it is time someone
 * spends with him, not another screen he has to work out alone.
 */
class PracticeFocusTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Carbon::setTestNow('2026-08-20 09:00:00');
        $this->category = Category::create(['name' => 'Fidel', 'slug' => 'fidel']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function itemWithHistory(string $word, int $correct, int $wrong, User $user, string $heard = 'x', int $level = 1, ?int $order = null): AmharicWord
    {
        $item = AmharicWord::create(['word' => $word, 'transliterations' => [$word], 'order' => $order]);
        $this->category->words()->attach($item->id, ['level' => $level]);

        foreach (range(1, $correct + $wrong) as $n) {
            $attempt = new SpeechAttempt([
                'user_id' => $user->id,
                'amharic_word_id' => $item->id,
                'transcription' => $n <= $correct ? $word : $heard,
                'is_correct' => $n <= $correct,
            ]);
            $attempt->created_at = Carbon::parse('2026-08-15 09:00:00')->addSeconds($n);
            $attempt->updated_at = $attempt->created_at;
            $attempt->save();
        }

        return $item;
    }

    public function test_it_lists_families_that_need_work_and_leaves_out_those_that_do_not(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->itemWithHistory('ጠ', 1, 19, $admin, heard: 'ደሮ', level: 5, order: 1);
        $this->itemWithHistory('ሰ', 18, 2, $admin, level: 9, order: 2);

        $response = $this->actingAs($admin)
            ->get(route('admin.practice-focus.index', ['category_id' => $this->category->id, 'user_id' => $admin->id]));

        $response->assertOk();

        $levels = collect($response->viewData('families'))->pluck('level')->all();

        $this->assertContains(5, $levels);
        $this->assertNotContains(9, $levels, 'a family he can do does not need your time');
    }

    /**
     * Get the first of a row and the rest usually follows, so the family whose
     * first letter is the problem is the one worth an hour.
     */
    public function test_it_puts_the_family_whose_first_letter_is_stuck_at_the_top(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // First letter fine, a later one stuck.
        $this->itemWithHistory('ሰ', 18, 2, $admin, level: 9, order: 1);
        $this->itemWithHistory('ሱ', 1, 19, $admin, level: 9, order: 2);

        // First letter itself stuck.
        $this->itemWithHistory('ጠ', 1, 19, $admin, level: 5, order: 3);
        $this->itemWithHistory('ጡ', 1, 19, $admin, level: 5, order: 4);

        $families = $this->actingAs($admin)
            ->get(route('admin.practice-focus.index', ['category_id' => $this->category->id, 'user_id' => $admin->id]))
            ->viewData('families');

        $this->assertSame(5, $families[0]['level']);
        $this->assertTrue($families[0]['first_stuck']);
        $this->assertSame('ጠ', $families[0]['first']['word'], 'the letter to start from');
        $this->assertFalse($families[1]['first_stuck']);
    }

    /**
     * The recogniser's own output next to each item, because whether the
     * trouble is his mouth or the machine's ear is usually obvious the moment
     * you see the same wrong word coming back every time.
     */
    public function test_it_shows_what_the_recogniser_actually_returned(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->itemWithHistory('ጠ', 1, 19, $admin, heard: 'ደሮ', level: 5, order: 1);

        $families = $this->actingAs($admin)
            ->get(route('admin.practice-focus.index', ['category_id' => $this->category->id, 'user_id' => $admin->id]))
            ->viewData('families');

        $this->assertSame('ደሮ', $families[0]['heard'][0]['text']);
        $this->assertSame(19, $families[0]['heard'][0]['times']);
    }

    /**
     * Grouped by consonant family, because the family is the unit that is
     * stuck: pulling one letter out and leaving its siblings in rotation just
     * spreads the same wall over seven items.
     */
    public function test_a_family_is_one_card_rather_than_a_letter_each(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        foreach ([['ጠ', 1], ['ጡ', 2], ['ጢ', 3]] as [$letter, $order]) {
            $this->itemWithHistory($letter, 1, 19, $admin, level: 5, order: $order);
        }

        $families = $this->actingAs($admin)
            ->get(route('admin.practice-focus.index', ['category_id' => $this->category->id, 'user_id' => $admin->id]))
            ->viewData('families');

        $this->assertCount(1, $families, 'three letters of one consonant is one thing to work on');
        $this->assertSame(3, $families[0]['stuck']);
        $this->assertSame(['ጠ', 'ጡ', 'ጢ'], array_column($families[0]['letters'], 'word'));
    }

    /**
     * Which category needs the time is the first thing you would otherwise
     * have to go and find out, so the page opens on it rather than on
     * whichever category happens to sort first.
     */
    public function test_it_opens_on_the_category_with_the_most_needing_help(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Sorts first by name, and has nothing wrong with it.
        $easy = Category::create(['name' => 'A quiet category', 'slug' => 'quiet']);
        $this->category = $easy;
        $this->itemWithHistory('ሰ', 18, 2, $admin);

        // Sorts last, and is where the trouble is.
        $hard = Category::create(['name' => 'Z busy category', 'slug' => 'busy']);
        $this->category = $hard;
        foreach (['ጠ', 'ጡ', 'ጢ'] as $letter) {
            $this->itemWithHistory($letter, 1, 19, $admin);
        }

        $response = $this->actingAs($admin)
            ->get(route('admin.practice-focus.index', ['user_id' => $admin->id]));

        $this->assertSame($hard->id, $response->viewData('category')->id);
        $this->assertSame(3, $response->viewData('needingHelp')[$hard->id]);
    }

    public function test_it_is_closed_to_people_who_are_not_admins(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('admin.practice-focus.index'))
            ->assertRedirect();
    }
}
