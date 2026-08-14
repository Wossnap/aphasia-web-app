<?php

namespace App\Services\Practice;

use App\Models\AmharicWord;
use App\Models\SpeechAttempt;
use Illuminate\Support\Facades\Cache;

/**
 * Which items get mistaken for each other, learned from his own attempts.
 *
 * Consonant families (see WordFamily) only catch items that are neighbours in
 * the alphabet. They do not catch ቃ coming back as ካ, or ሾ as ሶ — different
 * families, same trouble. Measured against the real log, families explain 34
 * confusion pairs and miss 110, and the misses are the larger ones. So this is
 * the main rule and the family rule is the fallback for items with too little
 * history to have taught us anything yet.
 *
 * It does not matter here whether a confusion is his mouth or the recogniser's
 * ear. If the app is going to mark ካ wrong straight after he missed ቃ, serving
 * it is a wall either way.
 */
class ConfusionGraph
{
    /**
     * Item ids that get confused with this one, in either direction.
     *
     * @return array<int, true> keyed by word id, for O(1) membership tests
     */
    public function siblingsOf(int $wordId, ?int $userId = null): array
    {
        return $this->graph($userId)[$wordId] ?? [];
    }

    public function areSiblings(int $a, int $b, ?int $userId = null): bool
    {
        return isset($this->graph($userId)[$a][$b]);
    }

    /**
     * The whole graph: word id => [confusable word id => true].
     *
     * Cached, because it reads the full attempt log. Any Accept changes the
     * answer, so the cache is short-lived rather than clever about
     * invalidation — it is a scheduling hint, not a verdict.
     */
    public function graph(?int $userId = null): array
    {
        $minimum = (int) config('practice.confusion.min_occurrences', 5);
        $ttl = (int) config('practice.confusion.cache_seconds', 900);
        $key = 'practice.confusion.' . ($userId ?? 'all') . '.' . $minimum;

        return Cache::remember($key, $ttl, fn () => $this->build($userId, $minimum));
    }

    private function build(?int $userId, int $minimum): array
    {
        // What each item would accept, so a transcription can be traced back to
        // the item it actually sounds like.
        $spokenAs = [];
        foreach (AmharicWord::query()->get(['id', 'word', 'transliterations']) as $word) {
            foreach (array_merge([$word->word], $word->transliterations ?? []) as $form) {
                $key = $this->normalise((string) $form);

                if ($key !== '') {
                    $spokenAs[$key][$word->id] = true;
                }
            }
        }

        $pairs = [];

        SpeechAttempt::query()
            ->where('is_correct', false)
            ->whereNotNull('amharic_word_id')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            // id is required for chunkById to page through the log.
            ->select(['id', 'amharic_word_id', 'transcription'])
            ->chunkById(2000, function ($attempts) use ($spokenAs, &$pairs) {
                foreach ($attempts as $attempt) {
                    $heard = $this->normalise((string) $attempt->transcription);

                    if ($heard === '') {
                        continue;
                    }

                    foreach ($spokenAs[$heard] ?? [] as $soundsLike => $_) {
                        if ($soundsLike === $attempt->amharic_word_id) {
                            continue;
                        }

                        $a = min($attempt->amharic_word_id, $soundsLike);
                        $b = max($attempt->amharic_word_id, $soundsLike);
                        $pairs["{$a}:{$b}"] = ($pairs["{$a}:{$b}"] ?? 0) + 1;
                    }
                }
            });

        $graph = [];

        foreach ($pairs as $pair => $times) {
            if ($times < $minimum) {
                continue;
            }

            [$a, $b] = array_map('intval', explode(':', $pair));
            $graph[$a][$b] = true;
            $graph[$b][$a] = true;
        }

        return $graph;
    }

    /**
     * Fold a transcription down to what was said, so "ራ ራ ራ" and "ራ" are
     * recognised as the same utterance — drilling one sound repeatedly is
     * exactly what practice sounds like.
     */
    private function normalise(string $text): string
    {
        $text = trim(mb_strtolower($text));

        if ($text === '') {
            return '';
        }

        $tokens = preg_split('/\s+/u', $text) ?: [];
        $unique = array_values(array_unique($tokens));

        return count($unique) === 1 ? $unique[0] : $text;
    }
}
