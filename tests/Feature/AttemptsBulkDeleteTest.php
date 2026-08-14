<?php

namespace Tests\Feature;

use App\Models\SpeechAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Clearing a run of noise out of the attempts log in one pass: the checked
 * rows go, the unchecked ones stay, and each row's recording goes with it.
 */
class AttemptsBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function attempt(User $user, ?string $audioPath = null): SpeechAttempt
    {
        return SpeechAttempt::create([
            'user_id' => $user->id,
            'transcription' => 'test',
            'is_correct' => false,
            'audio_path' => $audioPath,
        ]);
    }

    public function test_it_deletes_only_the_selected_attempts(): void
    {
        $admin = $this->admin();
        $doomed = [$this->attempt($admin), $this->attempt($admin)];
        $kept = $this->attempt($admin);

        $this->actingAs($admin)
            ->delete(route('admin.attempts.bulk-destroy'), [
                'ids' => [$doomed[0]->id, $doomed[1]->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('speech_attempts', ['id' => $doomed[0]->id]);
        $this->assertDatabaseMissing('speech_attempts', ['id' => $doomed[1]->id]);
        $this->assertDatabaseHas('speech_attempts', ['id' => $kept->id]);
    }

    public function test_it_removes_the_recording_from_disk(): void
    {
        $admin = $this->admin();

        $dir = public_path('audio/attempts');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $name = 'bulk-delete-test-' . uniqid() . '.webm';
        file_put_contents($dir . '/' . $name, 'audio');

        $attempt = $this->attempt($admin, $name);

        $this->actingAs($admin)
            ->delete(route('admin.attempts.bulk-destroy'), ['ids' => [$attempt->id]])
            ->assertRedirect();

        $this->assertFileDoesNotExist($dir . '/' . $name);
    }

    public function test_it_rejects_an_empty_selection(): void
    {
        $admin = $this->admin();
        $attempt = $this->attempt($admin);

        $this->actingAs($admin)
            ->from(route('admin.attempts.index'))
            ->delete(route('admin.attempts.bulk-destroy'), ['ids' => []])
            ->assertSessionHasErrors('ids');

        $this->assertDatabaseHas('speech_attempts', ['id' => $attempt->id]);
    }

    public function test_a_non_admin_cannot_bulk_delete(): void
    {
        $admin = $this->admin();
        $attempt = $this->attempt($admin);
        $other = User::factory()->create(['is_admin' => false]);

        $this->actingAs($other)
            ->delete(route('admin.attempts.bulk-destroy'), ['ids' => [$attempt->id]]);

        $this->assertDatabaseHas('speech_attempts', ['id' => $attempt->id]);
    }
}
