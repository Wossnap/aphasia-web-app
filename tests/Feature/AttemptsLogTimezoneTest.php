<?php

namespace Tests\Feature;

use App\Models\SpeechAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The attempts log stores UTC and displays the viewer's zone — including the
 * date filter, whose "day" is the viewer's day, not the server's.
 */
class AttemptsLogTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function attemptAt(User $user, string $time): SpeechAttempt
    {
        $attempt = new SpeechAttempt([
            'user_id' => $user->id,
            'transcription' => 'test',
            'is_correct' => true,
        ]);
        $attempt->created_at = Carbon::parse($time);
        $attempt->updated_at = Carbon::parse($time);
        $attempt->save();

        return $attempt;
    }

    public function test_the_listed_time_is_in_the_viewers_zone(): void
    {
        config(['app.display_timezone' => 'Africa/Addis_Ababa']);
        $admin = $this->admin();
        Carbon::setTestNow('2026-08-15 12:00:00');
        $this->attemptAt($admin, '2026-08-15 06:30:00');

        // 06:30 UTC is 09:30 in Addis Ababa (UTC+3).
        $this->actingAs($admin)
            ->get(route('admin.attempts.index'))
            ->assertOk()
            ->assertSee('Aug 15, 9:30 AM')
            ->assertDontSee('Aug 15, 6:30 AM');
    }

    public function test_the_browser_cookie_changes_the_listed_time(): void
    {
        $admin = $this->admin();
        Carbon::setTestNow('2026-08-15 12:00:00');
        $this->attemptAt($admin, '2026-08-15 06:30:00');

        // 06:30 UTC is 15:30 in Tokyo (UTC+9).
        $this->actingAs($admin)
            ->withUnencryptedCookie('display_tz', 'Asia/Tokyo')
            ->get(route('admin.attempts.index'))
            ->assertOk()
            ->assertSee('Aug 15, 3:30 PM');
    }

    public function test_the_date_filter_uses_the_viewers_day_boundaries(): void
    {
        config(['app.display_timezone' => 'Africa/Addis_Ababa']);
        $admin = $this->admin();
        Carbon::setTestNow('2026-08-16 12:00:00');

        // 21:30 UTC on the 14th is 00:30 on the 15th in Addis, so filtering
        // to the 15th must include it. 20:30 UTC on the 15th is 23:30 the
        // same local day and must survive too.
        $justAfterLocalMidnight = $this->attemptAt($admin, '2026-08-14 21:30:00');
        $lateLocalEvening = $this->attemptAt($admin, '2026-08-15 20:30:00');
        // 21:30 UTC on the 15th is already 00:30 on the 16th locally.
        $nextLocalDay = $this->attemptAt($admin, '2026-08-15 21:30:00');

        $response = $this->actingAs($admin)
            ->get(route('admin.attempts.index', ['from' => '2026-08-15', 'to' => '2026-08-15']))
            ->assertOk();

        $ids = $response->viewData('attempts')->pluck('id')->all();

        $this->assertContains($justAfterLocalMidnight->id, $ids);
        $this->assertContains($lateLocalEvening->id, $ids);
        $this->assertNotContains($nextLocalDay->id, $ids, 'that one is the next day where the viewer is');
    }

    public function test_a_malformed_filter_date_is_ignored_rather_than_fatal(): void
    {
        $admin = $this->admin();
        Carbon::setTestNow('2026-08-15 12:00:00');
        $this->attemptAt($admin, '2026-08-15 06:30:00');

        $this->actingAs($admin)
            ->get(route('admin.attempts.index', ['from' => 'not-a-date']))
            ->assertOk();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
