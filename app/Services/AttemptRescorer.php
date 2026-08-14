<?php

namespace App\Services;

use App\Models\AmharicWord;
use App\Models\SpeechAttempt;
use Illuminate\Database\Eloquent\Builder;

/**
 * Re-judges stored attempts against the transliterations a word accepts now.
 *
 * Accepting a transcription in the admin screen only ever flipped the one
 * attempt it was clicked on, so every earlier attempt that said the same thing
 * stayed marked wrong forever. The stored history therefore understates him,
 * and does so a little more with every Accept. This is what puts it right.
 */
class AttemptRescorer
{
    public function __construct(private TranscriptionMatcher $matcher)
    {
    }

    /**
     * Re-score every attempt the query selects.
     *
     * Upgrades (wrong -> correct) are the point of the exercise and are always
     * applied. Downgrades are held back unless asked for: a correct attempt is
     * something he was already told he got right, and taking that back on a
     * batch job is not a thing to do by default.
     *
     * @return array{scanned:int,upgraded:int,downgraded:int,by_word:array,downgrade_candidates:array}
     */
    public function rescore(Builder $query, bool $apply = true, bool $allowDowngrade = false): array
    {
        $scanned = 0;
        $upgraded = 0;
        $downgraded = 0;
        $byWord = [];
        $candidates = [];

        // The query never filters on is_correct, so writing that column here
        // cannot shift the rows a later chunk would see.
        $query->with('word')->chunkById(500, function ($attempts) use (
            $apply, $allowDowngrade, &$scanned, &$upgraded, &$downgraded, &$byWord, &$candidates
        ) {
            foreach ($attempts as $attempt) {
                $word = $attempt->word;

                if (!$word) {
                    continue;
                }

                $scanned++;

                $transliterations = $word->transliterations ?? [];
                $shouldBeCorrect = $this->matcher->matches($attempt->transcription, $transliterations);

                if ($shouldBeCorrect === $attempt->is_correct) {
                    continue;
                }

                $tally = $byWord[$word->id] ?? ['word' => $word->word, 'upgraded' => 0, 'downgraded' => 0];

                if ($shouldBeCorrect) {
                    $upgraded++;
                    $tally['upgraded']++;
                } else {
                    $downgraded++;
                    $tally['downgraded']++;
                    $candidates[] = [
                        'id' => $attempt->id,
                        'word' => $word->word,
                        'transcription' => $attempt->transcription,
                    ];
                }

                $byWord[$word->id] = $tally;

                if (!$apply || (!$shouldBeCorrect && !$allowDowngrade)) {
                    continue;
                }

                $attempt->is_correct = $shouldBeCorrect;
                // Keep the record self-explaining: the list stored on the
                // attempt is the one its verdict was actually decided against.
                $attempt->checked_transliterations = $transliterations;
                $attempt->save();
            }
        });

        return [
            'scanned' => $scanned,
            'upgraded' => $upgraded,
            'downgraded' => $downgraded,
            'by_word' => $byWord,
            'downgrade_candidates' => $candidates,
        ];
    }

    /**
     * Re-score one word's whole history. Called straight after an Accept, so
     * the daily review keeps the baseline honest without anyone remembering
     * to run the command.
     */
    public function rescoreWord(AmharicWord $word, bool $allowDowngrade = false): array
    {
        return $this->rescore(
            SpeechAttempt::query()->where('amharic_word_id', $word->id),
            apply: true,
            allowDowngrade: $allowDowngrade,
        );
    }
}
