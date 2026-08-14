<?php

namespace Tests\Feature;

use App\Models\SpeechAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The practice-block timeline on the analytics page: attempts split into
 * sittings by a pause, rather than collapsed into one number per day.
 */
class PracticeBlocksTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /**
     * created_at is not fillable, so it has to be set on the model rather
     * than passed to create() — Eloquent leaves an already-dirty timestamp
     * alone instead of stamping it with now().
     */
    private function attemptAt(?User $user, string $time, bool $correct = true): void
    {
        $attempt = new SpeechAttempt([
            'user_id' => $user?->id,
            'transcription' => 'test',
            'is_correct' => $correct,
        ]);
        $attempt->created_at = Carbon::parse($time);
        $attempt->updated_at = Carbon::parse($time);
        $attempt->save();
    }

    /** @return array<int, array> the block rows the view was handed */
    private function blockRows(User $admin, array $query = []): array
    {
        $response = $this->actingAs($admin)->get(route('admin.analytics.index', $query));
        $response->assertOk();

        return $response->viewData('blockRows');
    }

    public function test_a_gap_longer_than_the_threshold_starts_a_new_block(): void
    {
        $admin = $this->admin();
        Carbon::setTestNow('2026-08-14 21:00:00');

        // Three sittings: a morning one, one after a 2h42m gap, and one after
        // a 3h21m gap. Within each, attempts are minutes apart.
        $this->attemptAt($admin, '2026-08-14 09:12:00');
        $this->attemptAt($admin, '2026-08-14 09:30:00');
        $this->attemptAt($admin, '2026-08-14 09:48:00');
        $this->attemptAt($admin, '2026-08-14 12:30:00');
        $this->attemptAt($admin, '2026-08-14 12:44:00');
        $this->attemptAt($admin, '2026-08-14 16:05:00');

        $rows = $this->blockRows($admin, ['user_id' => $admin->id, 'gap' => 30]);

        $this->assertCount(1, $rows, 'one row for the one day practised');
        $blocks = $rows[0]['blocks'];
        $this->assertCount(3, $blocks);

        $this->assertSame('09:12', $blocks[0]['start']->format('H:i'));
        $this->assertSame('09:48', $blocks[0]['end']->format('H:i'));
        $this->assertSame(3, $blocks[0]['attempts']);
        $this->assertEqualsWithDelta(36.0, $blocks[0]['minutes'], 0.01);

        $this->assertSame('12:30', $blocks[1]['start']->format('H:i'));
        $this->assertSame(2, $blocks[1]['attempts']);

        // A single-attempt block is zero minutes long, not an invented span.
        $this->assertSame(1, $blocks[2]['attempts']);
        $this->assertEqualsWithDelta(0.0, $blocks[2]['minutes'], 0.01);
    }

    public function test_the_gap_setting_changes_how_attempts_are_split(): void
    {
        $admin = $this->admin();
        Carbon::setTestNow('2026-08-14 21:00:00');

        // 45 minutes apart: two blocks at a 30-minute gap, one at an hour.
        $this->attemptAt($admin, '2026-08-14 10:00:00');
        $this->attemptAt($admin, '2026-08-14 10:45:00');

        $this->assertCount(2, $this->blockRows($admin, ['user_id' => $admin->id, 'gap' => 30])[0]['blocks']);
        $this->assertCount(1, $this->blockRows($admin, ['user_id' => $admin->id, 'gap' => 60])[0]['blocks']);
    }

    public function test_a_pause_exactly_the_gap_length_stays_one_block(): void
    {
        $admin = $this->admin();
        Carbon::setTestNow('2026-08-14 21:00:00');

        $this->attemptAt($admin, '2026-08-14 10:00:00');
        $this->attemptAt($admin, '2026-08-14 10:30:00');

        $rows = $this->blockRows($admin, ['user_id' => $admin->id, 'gap' => 30]);
        $this->assertCount(1, $rows[0]['blocks'], 'the split is on longer-than, not at-least');
    }

    public function test_two_users_never_share_a_block(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create();
        Carbon::setTestNow('2026-08-14 21:00:00');

        // Interleaved in time: welded together they would read as one block.
        $this->attemptAt($admin, '2026-08-14 10:00:00');
        $this->attemptAt($other, '2026-08-14 10:05:00');
        $this->attemptAt($admin, '2026-08-14 10:10:00');
        $this->attemptAt($other, '2026-08-14 10:15:00');

        $rows = $this->blockRows($admin);

        $this->assertCount(2, $rows, 'one row per user per day');
        foreach ($rows as $row) {
            $this->assertCount(1, $row['blocks']);
            $this->assertSame(2, $row['blocks'][0]['attempts']);
        }
    }

    public function test_attempts_with_no_user_are_left_out(): void
    {
        $admin = $this->admin();
        Carbon::setTestNow('2026-08-14 21:00:00');

        $this->attemptAt(null, '2026-08-14 10:00:00');

        $this->assertSame([], $this->blockRows($admin));
    }

    public function test_rows_run_newest_first_and_report_accuracy(): void
    {
        $admin = $this->admin();
        Carbon::setTestNow('2026-08-14 21:00:00');

        $this->attemptAt($admin, '2026-08-12 10:00:00');
        $this->attemptAt($admin, '2026-08-14 10:00:00', true);
        $this->attemptAt($admin, '2026-08-14 10:05:00', false);
        $this->attemptAt($admin, '2026-08-14 10:10:00', true);
        $this->attemptAt($admin, '2026-08-14 10:15:00', true);

        $rows = $this->blockRows($admin, ['user_id' => $admin->id]);

        $this->assertSame('2026-08-14', $rows[0]['date']->toDateString());
        $this->assertSame('2026-08-12', $rows[1]['date']->toDateString());
        $this->assertSame(75, $rows[0]['blocks'][0]['accuracy']);
        $this->assertSame(4, $rows[0]['attempts']);
    }

    public function test_an_unknown_gap_falls_back_to_the_configured_default(): void
    {
        $admin = $this->admin();
        Carbon::setTestNow('2026-08-14 21:00:00');
        $this->attemptAt($admin, '2026-08-14 10:00:00');

        $response = $this->actingAs($admin)->get(route('admin.analytics.index', ['gap' => 7]));
        $response->assertOk();
        $this->assertSame(config('services.analytics.block_gap_minutes'), $response->viewData('gap'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
