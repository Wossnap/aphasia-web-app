<?php

namespace App\Services\Practice;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;

/**
 * Which rows are the same sound as each other.
 *
 * ሀ, ሐ and ኀ are three separate rows of the alphabet and one sound: all of
 * them are /h/ in modern Amharic, and his mouth does the same thing for each.
 * Working ሀ ሁ ሂ ሃ ሄ ህ ሆ and then ሐ ሑ ሒ ሓ ሔ ሕ ሖ and then ኀ ኁ ኂ ኃ ኄ ኅ ኆ is
 * doing one row three times while appearing to do three. The same goes for
 * ሠ/ሰ and ጸ/ፀ.
 *
 * Nothing needs to be told this. The rows already declare it: the accepted
 * forms of ሀ include ሐ and ኀ and "ha", because that is what the recogniser
 * returns and what has been accepted. Rows that accept each other's forms are
 * the same sound.
 */
class RowSounds
{
    /**
     * Level => sound group id, for every level in the category.
     *
     * Levels in the same group are the same sound and should not both turn up
     * in one sitting.
     *
     * @return array<int, int>
     */
    public function groups(Category $category): array
    {
        $ttl = (int) config('practice.confusion.cache_seconds', 900);

        return Cache::remember(
            'practice.row-sounds.' . $category->id,
            $ttl,
            fn () => $this->build($category),
        );
    }

    /** Whether two levels are the same sound. */
    public function sameSound(Category $category, ?int $a, ?int $b): bool
    {
        if ($a === null || $b === null || $a === $b) {
            return false;
        }

        $groups = $this->groups($category);

        return isset($groups[$a], $groups[$b]) && $groups[$a] === $groups[$b];
    }

    /** @return array<int, int> */
    private function build(Category $category): array
    {
        $maxLevels = (int) config('practice.sounds.max_levels_per_form', 5);

        // The first letter of each row, which is the one that names the sound.
        $firsts = $category->words()
            ->get(['amharic_words.id', 'amharic_words.word', 'amharic_words.transliterations', 'amharic_words.order'])
            ->filter(fn ($w) => ($w->pivot->level ?? 0) > 0)
            ->groupBy(fn ($w) => (int) $w->pivot->level)
            ->map(fn ($rows) => $rows->sortBy(fn ($w) => $w->order ?? PHP_INT_MAX)->first());

        // Latin spellings only — "ha", "seh", "tse". They are the phonetic key
        // and they are specific: ሀ, ሐ and ኀ all read "ha", which is the fact
        // being looked for. The Amharic forms are far too loose to use here;
        // going by those merged ሀ with ለ through a shared stray accept.
        $rowsPerForm = [];

        foreach ($firsts as $level => $word) {
            foreach ($word->transliterations ?? [] as $form) {
                $key = trim(mb_strtolower((string) $form));

                if ($key !== '' && preg_match('/^[a-z]+$/', $key)) {
                    $rowsPerForm[$key][(int) $level] = true;
                }
            }
        }

        $links = [];

        foreach ($rowsPerForm as $levels) {
            // A spelling shared by more rows than this is a transcription
            // habit rather than a sound.
            if (count($levels) < 2 || count($levels) > $maxLevels) {
                continue;
            }

            $levels = array_keys($levels);

            foreach ($levels as $a) {
                foreach ($levels as $b) {
                    if ($a !== $b) {
                        $links[$a][$b] = true;
                    }
                }
            }
        }

        // Connected components: ሀ links to ሐ, ሐ links to ኀ, so all three are
        // one sound even where no single form is shared by all of them.
        $group = [];
        $next = 0;

        foreach (array_keys($firsts->all()) as $level) {
            $level = (int) $level;

            if (isset($group[$level])) {
                continue;
            }

            $id = $next++;
            $queue = [$level];

            while ($queue) {
                $at = array_pop($queue);

                if (isset($group[$at])) {
                    continue;
                }

                $group[$at] = $id;

                foreach (array_keys($links[$at] ?? []) as $neighbour) {
                    if (!isset($group[$neighbour])) {
                        $queue[] = $neighbour;
                    }
                }
            }
        }

        return $group;
    }
}
