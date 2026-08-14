<?php

namespace App\Services\Practice;

/**
 * Which items are the "same thing" in a different dress.
 *
 * ጠ ጡ ጢ ጣ ጤ ጥ ጦ are one consonant in seven vowel orders. Serving ጡ straight
 * after he has just missed ጠ is not moving on — it is the same wall with a
 * different hat. The Ethiopic block lays each consonant out in eight
 * consecutive code points, so the grouping is arithmetic on the character and
 * needs no table, no config and no category to be told apart.
 *
 * Anything that is not a single Ethiopic character has no family, which is
 * what makes this safe to apply everywhere: ሰው and ዳቦ are unrelated words, so
 * they answer null and the caller treats them as never-siblings. Nothing here
 * knows or asks which category it is looking at.
 */
class WordFamily
{
    /** First code point of the Ethiopic block. */
    private const BLOCK_START = 0x1200;

    /** Last code point of the Ethiopic block proper. */
    private const BLOCK_END = 0x137F;

    /** Vowel orders per consonant, i.e. the stride between families. */
    private const ORDERS = 8;

    /**
     * The consonant family of a single Ge'ez syllable, or null when the item
     * is not one — a whole word, a number, a phrase.
     */
    public function of(?string $word): ?int
    {
        if ($word === null) {
            return null;
        }

        $word = trim($word);

        if (mb_strlen($word) !== 1) {
            return null;
        }

        $codepoint = mb_ord($word, 'UTF-8');

        if ($codepoint === false || $codepoint < self::BLOCK_START || $codepoint > self::BLOCK_END) {
            return null;
        }

        return intdiv($codepoint - self::BLOCK_START, self::ORDERS);
    }

    /**
     * Whether two items share a consonant family.
     *
     * Two items with no family are never siblings, even though both are null —
     * ሰው and ዳቦ have nothing to do with each other.
     */
    public function areSiblings(?string $a, ?string $b): bool
    {
        $familyA = $this->of($a);

        return $familyA !== null && $familyA === $this->of($b);
    }
}
