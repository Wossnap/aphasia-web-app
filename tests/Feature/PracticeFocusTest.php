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

    private function itemWithHistory(string $word, int $correct, int $wrong, User $user, string $heard = 'x'): AmharicWord
    {
        $item = AmharicWord::create(['word' => $word, 'transliterations' => [$word]]);
        $this->category->words()->attach($item->id, ['level' => 1]);

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

    public function test_it_lists_what_he_cannot_do_alone_and_leaves_out_what_he_can(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $stuck = $this->itemWithHistory('ጠ', 1, 19, $admin, heard: 'ደሮ');
        $fine = $this->itemWithHistory('ሰ', 18, 2, $admin);

        $response = $this->actingAs($admin)
            ->get(route('admin.practice-focus.index', ['category_id' => $this->category->id, 'user_id' => $admin->id]));

        $response->assertOk();

        $words = collect($response->viewData('rows'))->pluck('word')->all();

        $this->assertContains($stuck->word, $words);
        $this->assertNotContains($fine->word, $words, 'an item he can do does not need your time');
    }

    /**
     * The recogniser's own output next to each item, because whether the
     * trouble is his mouth or the machine's ear is usually obvious the moment
     * you see the same wrong word coming back every time.
     */
    public function test_it_shows_what_the_recogniser_actually_returned(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->itemWithHistory('ጠ', 1, 19, $admin, heard: 'ደሮ');

        $rows = $this->actingAs($admin)
            ->get(route('admin.practice-focus.index', ['category_id' => $this->category->id, 'user_id' => $admin->id]))
            ->viewData('rows');

        $this->assertSame('ደሮ', $rows[0]['heard'][0]['text']);
        $this->assertSame(19, $rows[0]['heard'][0]['times']);
    }

    /**
     * Grouped by consonant family, because the family is the unit that is
     * stuck: pulling one letter out and leaving its siblings in rotation just
     * spreads the same wall over seven items.
     */
    public function test_it_groups_stuck_letters_by_family(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        foreach (['ጠ', 'ጡ', 'ጢ'] as $letter) {
            $this->itemWithHistory($letter, 1, 19, $admin);
        }

        $families = $this->actingAs($admin)
            ->get(route('admin.practice-focus.index', ['category_id' => $this->category->id, 'user_id' => $admin->id]))
            ->viewData('familyRows');

        $this->assertCount(1, $families, 'three letters of one consonant is one thing to work on');
        $this->assertSame(3, $families[0]['count']);
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
