<?php

namespace Tests\Feature;

use App\Models\AmharicWord;
use App\Models\Category;
use App\Models\SpeechAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * A whole category, level by level, first to last.
 *
 * The work-with-him list answers "where should I spend an hour" and shows
 * only what needs a person. This answers the other question — how is he doing
 * overall — and so has to show everything, including the rows he has and the
 * rows he has not started.
 */
class CategoryProgressTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-20 09:00:00');
        $this->category = Category::create(['name' => 'Fidel', 'slug' => 'fidel']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function letter(string $word, int $level, int $order, ?int $correct = null, int $wrong = 0): AmharicWord
    {
        $item = AmharicWord::create(['word' => $word, 'transliterations' => [$word], 'order' => $order]);
        $this->category->words()->attach($item->id, ['level' => $level]);

        foreach (range(1, ($correct ?? 0) + $wrong) as $n) {
            $attempt = new SpeechAttempt([
                'user_id' => 1,
                'amharic_word_id' => $item->id,
                'transcription' => 'x',
                'is_correct' => $n <= $correct,
            ]);
            $attempt->created_at = Carbon::parse('2026-08-15 09:00:00')->addSeconds($n);
            $attempt->updated_at = $attempt->created_at;
            $attempt->save();
        }

        return $item;
    }

    private function levels(User $admin): array
    {
        return $this->actingAs($admin)
            ->get(route('admin.progress-by-level.index', [
                'category_id' => $this->category->id,
                'user_id' => 1,
            ]))
            ->viewData('levels')
            ->all();
    }

    public function test_it_shows_every_level_from_the_first_to_the_last(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'id' => 1]);

        $this->letter('ሰ', level: 9, order: 3, correct: 18, wrong: 2);
        $this->letter('ሀ', level: 1, order: 1, correct: 16, wrong: 4);
        $this->letter('ጠ', level: 27, order: 2, correct: 1, wrong: 19);

        $levels = $this->levels($admin);

        $this->assertSame([1, 9, 27], array_column($levels, 'level'), 'first level to last');
    }

    /**
     * Unlike the work-with-him list, a level he has mastered still belongs
     * here — the point is the whole picture, not only the trouble.
     */
    public function test_it_keeps_levels_he_is_good_at(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'id' => 1]);

        $this->letter('ሰ', level: 9, order: 1, correct: 19, wrong: 1);

        $levels = $this->levels($admin);

        $this->assertCount(1, $levels);
        $this->assertSame(0.95, round($levels[0]['accuracy'], 2));
    }

    public function test_a_level_he_has_never_tried_reads_as_not_started(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'id' => 1]);

        $this->letter('ጰ', level: 30, order: 1);

        $levels = $this->levels($admin);

        $this->assertNull($levels[0]['accuracy'], 'never attempted is unknown, not zero');
        $this->assertSame(1, $levels[0]['untried']);
    }

    public function test_letters_keep_the_categorys_own_order_within_a_level(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'id' => 1]);

        // Created out of sequence on purpose.
        $this->letter('ሂ', level: 1, order: 3, correct: 10, wrong: 10);
        $this->letter('ሀ', level: 1, order: 1, correct: 10, wrong: 10);
        $this->letter('ሁ', level: 1, order: 2, correct: 10, wrong: 10);

        $levels = $this->levels($admin);

        $this->assertSame(['ሀ', 'ሁ', 'ሂ'], array_column($levels[0]['letters'], 'word'));
    }

    public function test_it_is_closed_to_people_who_are_not_admins(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('admin.progress-by-level.index'))
            ->assertRedirect();
    }
}
