<?php

namespace Tests\Feature;

use App\Models\AmharicWord;
use App\Models\SpeechAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Accepting a transcription is a statement about the sound, not about one
 * recording — so it has to reach every earlier attempt that said the same
 * thing. Marking only the clicked attempt is what left the stored history
 * understating him, a little more with every Accept.
 */
class AttemptRescoreTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function word(string $word, array $transliterations = []): AmharicWord
    {
        return AmharicWord::create([
            'word' => $word,
            'transliterations' => $transliterations,
        ]);
    }

    private function attempt(AmharicWord $word, string $transcription, bool $correct = false): SpeechAttempt
    {
        return SpeechAttempt::create([
            'amharic_word_id' => $word->id,
            'transcription' => $transcription,
            'is_correct' => $correct,
        ]);
    }

    public function test_accepting_a_transcription_rescores_earlier_attempts_that_said_the_same_thing(): void
    {
        $word = $this->word('ደ', ['ደ']);
        $old = $this->attempt($word, 'ደሮ');
        $alsoOld = $this->attempt($word, 'ደሮ');
        $clicked = $this->attempt($word, 'ደሮ');

        $this->actingAs($this->admin())
            ->post(route('admin.attempts.add-transliteration', $clicked))
            ->assertRedirect();

        $this->assertTrue($old->fresh()->is_correct);
        $this->assertTrue($alsoOld->fresh()->is_correct);
        $this->assertTrue($clicked->fresh()->is_correct);
    }

    /**
     * The point of matching on substring rather than equality: drilling one
     * letter repeatedly is exactly what practice sounds like, and the
     * recogniser hands back the whole utterance.
     */
    public function test_accepting_a_transcription_also_covers_it_repeated(): void
    {
        $word = $this->word('ደ', ['ደ']);
        $repeated = $this->attempt($word, 'ደሮ ደሮ');
        $trailing = $this->attempt($word, 'ደሮ ደሮ ደሮ');
        $clicked = $this->attempt($word, 'ደሮ');

        $this->actingAs($this->admin())
            ->post(route('admin.attempts.add-transliteration', $clicked));

        $this->assertTrue($repeated->fresh()->is_correct);
        $this->assertTrue($trailing->fresh()->is_correct);
    }

    public function test_accepting_one_word_leaves_other_words_alone(): void
    {
        $de = $this->word('ደ', ['ደ']);
        $te = $this->word('ተ', ['ተ']);
        $otherWord = $this->attempt($te, 'ደሮ');
        $clicked = $this->attempt($de, 'ደሮ');

        $this->actingAs($this->admin())
            ->post(route('admin.attempts.add-transliteration', $clicked));

        $this->assertFalse($otherWord->fresh()->is_correct);
    }

    public function test_the_command_reports_without_writing_on_dry_run(): void
    {
        $word = $this->word('ደ', ['ደ', 'ደሮ']);
        $stale = $this->attempt($word, 'ደሮ');

        $this->artisan('attempts:rescore --dry-run')
            ->expectsOutputToContain('Would flip wrong -> correct: 1')
            ->assertSuccessful();

        $this->assertFalse($stale->fresh()->is_correct, 'a dry run must not write');

        $this->artisan('attempts:rescore')->assertSuccessful();

        $this->assertTrue($stale->fresh()->is_correct);
    }

    /**
     * A correct attempt is something he was already told he got right.
     * Taking that back is a decision someone makes, not a side effect of a
     * batch job, so the command reports it and stops there.
     */
    public function test_downgrades_are_reported_but_not_applied_by_default(): void
    {
        $word = $this->word('ደ', ['ደ']);
        $unsupported = $this->attempt($word, 'ሮ ሮ', correct: true);

        $this->artisan('attempts:rescore')
            ->expectsOutputToContain('Correct -> wrong: 1')
            ->assertSuccessful();

        $this->assertTrue($unsupported->fresh()->is_correct);

        $this->artisan('attempts:rescore --allow-downgrade')->assertSuccessful();

        $this->assertFalse($unsupported->fresh()->is_correct);
    }

    public function test_the_word_editor_rescores_when_a_transliteration_is_added(): void
    {
        $word = $this->word('ደ', ['ደ']);
        $stale = $this->attempt($word, 'ደሮ');

        $this->actingAs($this->admin())
            ->put(route('admin.words.update', $word), [
                'word' => 'ደ',
                'meaning' => null,
                'transliterations' => 'ደ, ደሮ',
            ])
            ->assertRedirect();

        $this->assertTrue($stale->fresh()->is_correct);
    }

    public function test_scoring_ignores_an_empty_transliteration_rather_than_passing_everything(): void
    {
        $word = $this->word('ደ', ['', 'ደ']);
        $unrelated = $this->attempt($word, 'ሮ');

        $this->artisan('attempts:rescore')->assertSuccessful();

        $this->assertFalse($unrelated->fresh()->is_correct);
    }
}
