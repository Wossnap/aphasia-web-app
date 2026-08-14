<?php

namespace App\Services;

/**
 * The one place that decides whether a recogniser transcription counts as
 * having said a word.
 *
 * Live scoring and the rescore command have to apply exactly the same rule.
 * If they ever drift, `attempts:rescore` starts rewriting history against a
 * rule the app itself never used, and the accuracy numbers stop meaning
 * anything. So everything that judges an attempt comes through here.
 */
class TranscriptionMatcher
{
    /**
     * Whether the transcription contains any accepted transliteration.
     */
    public function matches(?string $transcription, ?array $transliterations): bool
    {
        return $this->matched($transcription, $transliterations) !== null;
    }

    /**
     * The transliteration that earned the pass, or null if none did.
     *
     * Substring rather than equality, deliberately: the recogniser returns the
     * whole utterance, so drilling one letter three times ("ራ ራ ራ") still has
     * to count as having said it.
     */
    public function matched(?string $transcription, ?array $transliterations): ?string
    {
        if ($transcription === null || $transliterations === null) {
            return null;
        }

        $spoken = trim(mb_strtolower($transcription));

        if ($spoken === '') {
            return null;
        }

        foreach ($transliterations as $transliteration) {
            $candidate = trim(mb_strtolower((string) $transliteration));

            // An empty entry is a substring of every string, and would quietly
            // pass every future attempt on that word.
            if ($candidate === '') {
                continue;
            }

            if (str_contains($spoken, $candidate)) {
                return (string) $transliteration;
            }
        }

        return null;
    }
}
