<?php

declare(strict_types=1);

namespace Vellum\Answers;

/**
 * Steps 1 and 5a of the Porter stemmer: plurals, -ed and -ing endings, and a
 * final e, so "gates", "gated" and "gating" all match "gate", and "configured"
 * matches "configure". The steps in between, which cut endings like -ation and
 * -ness, are left out, since they merge words docs keep apart.
 * resources/js/search.js runs the same rules.
 */
final class Stemmer
{
    public static function stem(string $word): string
    {
        if (strlen($word) <= 2 || preg_match('/^[a-z]+$/', $word) !== 1) {
            return $word;
        }

        // Step 1a: plurals.
        if (str_ends_with($word, 'sses') || str_ends_with($word, 'ies')) {
            $word = substr($word, 0, -2);
        } elseif (! str_ends_with($word, 'ss') && str_ends_with($word, 's')) {
            $word = substr($word, 0, -1);
        }

        // Step 1b: -eed, -ed and -ing.
        if (str_ends_with($word, 'eed')) {
            if (self::measure(substr($word, 0, -3)) > 0) {
                $word = substr($word, 0, -1);
            }
        } else {
            foreach (['ed', 'ing'] as $ending) {
                $stem = substr($word, 0, -strlen($ending));

                if (str_ends_with($word, $ending) && self::hasVowel($stem)) {
                    $word = self::tidy($stem);

                    break;
                }
            }
        }

        // Step 1c: a final y after a vowel-bearing stem becomes i.
        if (str_ends_with($word, 'y') && self::hasVowel(substr($word, 0, -1))) {
            $word = substr($word, 0, -1).'i';
        }

        // Step 5a: a final e, unless the stem is short and ends consonant,
        // vowel, consonant ("gate", "file" and "page" keep theirs).
        if (str_ends_with($word, 'e')) {
            $stem = substr($word, 0, -1);
            $m = self::measure($stem);

            if ($m > 1 || ($m === 1 && ! self::endsCvc($stem))) {
                $word = $stem;
            }
        }

        return $word;
    }

    /**
     * What is left once -ed or -ing is cut: "gat" back to "gate", "hopp" to
     * "hop", "fil" to "file".
     */
    private static function tidy(string $stem): string
    {
        if (str_ends_with($stem, 'at') || str_ends_with($stem, 'bl') || str_ends_with($stem, 'iz')) {
            return $stem.'e';
        }

        $length = strlen($stem);

        if ($length >= 2 && $stem[$length - 1] === $stem[$length - 2] && self::consonant($stem, $length - 1) && ! in_array($stem[$length - 1], ['l', 's', 'z'], true)) {
            return substr($stem, 0, -1);
        }

        if (self::measure($stem) === 1 && self::endsCvc($stem)) {
            return $stem.'e';
        }

        return $stem;
    }

    private static function consonant(string $word, int $i): bool
    {
        return match ($word[$i]) {
            'a', 'e', 'i', 'o', 'u' => false,
            'y' => $i === 0 || ! self::consonant($word, $i - 1),
            default => true,
        };
    }

    /**
     * How many vowel-consonant sequences the word has: Porter's m.
     */
    private static function measure(string $word): int
    {
        $m = 0;
        $vowel = false;

        for ($i = 0, $length = strlen($word); $i < $length; $i++) {
            if (! self::consonant($word, $i)) {
                $vowel = true;
            } elseif ($vowel) {
                $m++;
                $vowel = false;
            }
        }

        return $m;
    }

    private static function hasVowel(string $word): bool
    {
        for ($i = 0, $length = strlen($word); $i < $length; $i++) {
            if (! self::consonant($word, $i)) {
                return true;
            }
        }

        return false;
    }

    private static function endsCvc(string $word): bool
    {
        $length = strlen($word);

        return $length >= 3
            && self::consonant($word, $length - 3)
            && ! self::consonant($word, $length - 2)
            && self::consonant($word, $length - 1)
            && ! in_array($word[$length - 1], ['w', 'x', 'y'], true);
    }
}
