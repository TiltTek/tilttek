<?php

declare(strict_types=1);

namespace Tilttek\Generator\Grammar;

use Override;

/**
 * The English rules: `a` and `an` by the sound of the next word (`an apple`, `a unicorn`, `an hour`), the articles, the
 * regular plural with the common irregular ones, and no feminine form.
 */
final readonly class English implements Grammar
{
    private const array CONSONANT_SOUND = ['uni', 'use', 'usu', 'uti', 'ure', 'eu', 'ewe', 'one', 'once', 'ubi'];

    private const array VOWEL_SOUND = ['hour', 'honest', 'honor', 'honour', 'heir'];

    private const array IRREGULAR = [
        'child' => 'children', 'deer' => 'deer', 'die' => 'dice', 'dwarf' => 'dwarves', 'elf' => 'elves',
        'fish' => 'fish', 'foot' => 'feet', 'goose' => 'geese', 'half' => 'halves', 'knife' => 'knives',
        'leaf' => 'leaves', 'life' => 'lives', 'man' => 'men', 'mouse' => 'mice', 'ox' => 'oxen', 'person' => 'people',
        'sheep' => 'sheep', 'thief' => 'thieves', 'tooth' => 'teeth', 'wolf' => 'wolves', 'woman' => 'women',
    ];

    #[Override]
    public function elide(string $text): string
    {
        [$words, $gaps] = Words::split($text);

        foreach ($words as $index => $word) {
            $next = $words[$index + 1] ?? '';

            if ('' !== $next && !str_starts_with($next, self::KEEP) && \in_array(strtolower($word), ['a', 'an'], true)) {
                $words[$index] = Words::sameCase($this->startsWithVowelSound($next) ? 'an' : 'a', $word);
            }
        }

        return Words::glue($words, $gaps);
    }

    #[Override]
    public function article(string $word, ArticleKind $kind, Gender $gender, bool $plural): string
    {
        return match ($kind) {
            ArticleKind::Definite => 'the '.$word,
            ArticleKind::Indefinite => $plural ? $word : $this->elide('a '.$word),
            ArticleKind::Partitive => 'some '.$word,
            ArticleKind::Demonstrative => ($plural ? 'these ' : 'this ').$word,
        };
    }

    #[Override]
    public function feminine(string $word): string
    {
        return $word;
    }

    #[Override]
    public function plural(string $word): string
    {
        $irregular = self::IRREGULAR[strtolower($word)] ?? null;

        return match (true) {
            null !== $irregular => Words::sameCase($irregular, $word),
            1 === preg_match('/(s|x|z|ch|sh)$/i', $word) => $word.'es',
            1 === preg_match('/[^aeiou]y$/i', $word) => substr($word, 0, -1).'ies',
            default => $word.'s',
        };
    }

    #[Override]
    public function isPlural(int $count): bool
    {
        return 1 !== abs($count);
    }

    private function startsWithVowelSound(string $word): bool
    {
        $lower = strtolower($word);

        foreach (self::VOWEL_SOUND as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return true;
            }
        }

        foreach (self::CONSONANT_SOUND as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return false;
            }
        }

        return str_contains('aeiou', $lower[0]);
    }
}
