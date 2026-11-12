<?php

declare(strict_types=1);

namespace Tilttek\Generator\Grammar;

/**
 * A text cut into words and the spaces between them, for the rules that look at a word and the next one.
 *
 * @internal
 */
final class Words
{
    private function __construct()
    {
    }

    /**
     * The words and, for each, the spaces after it; a text that starts or ends with spaces has an empty first or last
     * word.
     *
     * @return array{list<string>, list<string>}
     */
    public static function split(string $text): array
    {
        $words = [];
        $gaps = [];

        $tokens = preg_split('/(\s+)/u', $text, -1, \PREG_SPLIT_DELIM_CAPTURE);

        foreach (false === $tokens ? [$text] : $tokens as $index => $token) {
            if (0 === $index % 2) {
                $words[] = $token;
            } else {
                $gaps[] = $token;
            }
        }

        $gaps[] = '';

        return [$words, $gaps];
    }

    /**
     * Replaces the word at this index and the next one by one word.
     *
     * @param list<string> $words
     * @param list<string> $gaps
     *
     * @return array{list<string>, list<string>}
     */
    public static function join(array $words, array $gaps, int $index, string $word): array
    {
        $words[$index] = $word;
        unset($words[$index + 1], $gaps[$index]);

        return [array_values($words), array_values($gaps)];
    }

    /**
     * Joins each word found in the table with the next one when the table has a contraction for the pair, compared
     * without regard to case — the words of the table are written in ASCII lower case: `['zu' => ['dem' => 'zum']]`
     * turns `zu dem` into `zum` and `Zu dem` into `Zum`.
     *
     * @param list<string>                         $words
     * @param list<string>                         $gaps
     * @param array<string, array<string, string>> $table
     *
     * @return array{list<string>, list<string>}
     */
    public static function contract(array $words, array $gaps, array $table): array
    {
        for ($i = 0; $i < \count($words) - 1; ++$i) {
            $contracted = $table[strtolower($words[$i])][strtolower($words[$i + 1])] ?? null;

            if (null !== $contracted) {
                [$words, $gaps] = self::join($words, $gaps, $i, self::sameCase($contracted, $words[$i]));
            }
        }

        return [$words, $gaps];
    }

    /**
     * The text again, without the mark of the words kept as written: a rule that compares a word with its table never
     * finds a word that starts with the mark, so the pair it is part of is left alone.
     *
     * @param list<string> $words
     * @param list<string> $gaps
     */
    public static function glue(array $words, array $gaps): string
    {
        $text = '';

        foreach ($words as $index => $word) {
            $text .= (str_starts_with($word, Grammar::KEEP) ? substr($word, 1) : $word).$gaps[$index];
        }

        return $text;
    }

    /**
     * The replacement with a capital first letter when the original has one.
     */
    public static function sameCase(string $replacement, string $original): string
    {
        $first = mb_substr($original, 0, 1);

        return mb_strtolower($first) === $first ? $replacement : mb_strtoupper(mb_substr($replacement, 0, 1)).mb_substr($replacement, 1);
    }
}
