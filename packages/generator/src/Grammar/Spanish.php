<?php

declare(strict_types=1);

namespace Tilttek\Generator\Grammar;

use Override;

/**
 * The Spanish rules: the articles by gender and number (`el`, `la`, `los`, `las`; `un`, `una`, `unos`, `unas`; `este`,
 * `esta`; `lo` for the neuter), `el` and `un` before a feminine word whose first syllable is a stressed a (`el agua`,
 * `un hacha`), the contractions `de el` → `del` and `a el` → `al` — but not before a name that starts with `El`
 * (`de El Salvador`) —, and the regular feminine and plural with their
 * written accents (`canción` → `canciones`, `joven` → `jóvenes`). The common words with a stressed a are built in and
 * an application adds its own.
 */
final readonly class Spanish implements Grammar
{
    private const array CONTRACTED = ['de' => ['el' => 'del'], 'a' => ['el' => 'al']];

    private const array STRESSED_A = [
        'agua', 'águila', 'ala', 'alba', 'alma', 'alta', 'ama', 'ancla', 'ánfora', 'ansia', 'arca', 'área', 'arma',
        'arpa', 'asa', 'asma', 'aula', 'ave', 'haba', 'habla', 'hacha', 'hada', 'hambre', 'hampa',
    ];

    private const array INVARIABLE_FEMININE = [
        'afín', 'anterior', 'cortés', 'descortés', 'exterior', 'inferior', 'interior', 'mayor', 'mejor', 'menor',
        'peor', 'posterior', 'superior', 'ulterior',
    ];

    private const array IRREGULAR = [
        'carácter' => 'caracteres', 'club' => 'clubes', 'espécimen' => 'especímenes', 'régimen' => 'regímenes',
    ];

    private const string VOWELS = 'aeiouáéíóúü';

    private const array STRESS = ['a' => 'á', 'e' => 'é', 'i' => 'í', 'o' => 'ó', 'u' => 'ú'];

    /**
     * @var list<string>
     */
    private array $stressedA;

    /**
     * @param list<string> $stressedA more feminine words whose first syllable is a stressed a
     */
    public function __construct(array $stressedA = [])
    {
        $this->stressedA = [...self::STRESSED_A, ...array_map(mb_strtolower(...), $stressedA)];
    }

    #[Override]
    public function elide(string $text): string
    {
        [$words, $gaps] = Words::split($text);

        for ($i = 0; $i < \count($words) - 1; ++$i) {
            $lower = strtolower($words[$i]);

            if (\in_array($lower, ['la', 'una'], true) && $this->startsWithStressedA($words[$i + 1])) {
                $words[$i] = Words::sameCase('la' === $lower ? 'el' : 'un', $words[$i]);
            }
        }

        for ($i = 0; $i < \count($words) - 1; ++$i) {
            $contracted = self::CONTRACTED[strtolower($words[$i])][strtolower($words[$i + 1])] ?? null;

            if (null !== $contracted && ('e' === Words::sameCase('e', $words[$i + 1]) || 'E' === Words::sameCase('e', $words[$i]))) {
                [$words, $gaps] = Words::join($words, $gaps, $i, Words::sameCase($contracted, $words[$i]));
            }
        }

        return Words::glue($words, $gaps);
    }

    #[Override]
    public function article(string $word, ArticleKind $kind, Gender $gender, bool $plural): string
    {
        $feminine = Gender::Feminine === $gender;
        $article = match ($kind) {
            ArticleKind::Definite => match (true) {
                $plural => $feminine ? 'las' : 'los',
                Gender::Neuter === $gender => 'lo',
                default => $feminine ? 'la' : 'el',
            },
            ArticleKind::Indefinite => $plural ? ($feminine ? 'unas' : 'unos') : ($feminine ? 'una' : 'un'),
            ArticleKind::Partitive => '',
            ArticleKind::Demonstrative => $plural ? ($feminine ? 'estas' : 'estos') : ($feminine ? 'esta' : 'este'),
        };

        return '' === $article ? $word : $this->elide($article.' '.$word);
    }

    #[Override]
    public function feminine(string $word): string
    {
        $lower = mb_strtolower($word);

        return match (true) {
            \in_array($lower, self::INVARIABLE_FEMININE, true) => $word,
            str_ends_with($lower, 'o') => substr($word, 0, -1).'a',
            str_ends_with($lower, 'or') => $word.'a',
            str_ends_with($lower, 'és'), 1 === preg_match('/(á|é|í|ó)n$/', $lower) => $this->unstressLast($word).'a',
            default => $word,
        };
    }

    #[Override]
    public function plural(string $word): string
    {
        $lower = mb_strtolower($word);
        $irregular = self::IRREGULAR[$lower] ?? null;

        return match (true) {
            null !== $irregular => Words::sameCase($irregular, $word),
            1 === preg_match('/([aeiou]|á|é|ó)$/', $lower) => $word.'s',
            str_ends_with($lower, 'z') => substr($word, 0, -1).'ces',
            1 === preg_match('/(á|é|í|ó|ú)[^aeiou]+$/', $lower) => $this->unstressLast($word).'es',
            str_ends_with($lower, 's'), str_ends_with($lower, 'x') => preg_match_all('/['.self::VOWELS.']+/u', $lower) > 1 ? $word : $word.'es',
            0 === preg_match('/á|é|í|ó|ú/', $lower) => $this->stressBeforeLast($word).'es',
            default => $word.'es',
        };
    }

    #[Override]
    public function isPlural(int $count): bool
    {
        return 1 !== abs($count);
    }

    private function startsWithStressedA(string $word): bool
    {
        return \in_array(rtrim(mb_strtolower($word), '.,;:!?»"\')'), $this->stressedA, true);
    }

    /**
     * The word without the written accent of its last syllable, which an added syllable makes useless — except on an
     * i or a u that stands apart from the vowel before it (`país` → `países`).
     */
    private function unstressLast(string $word): string
    {
        return (string) preg_replace_callback(
            '/(['.self::VOWELS.']?)([áéíóú])([^'.self::VOWELS.']*)$/iu',
            static fn (array $match): string => $match[1].('' !== $match[1] && 1 === preg_match('/[íú]/iu', $match[2]) ? $match[2] : strtr($match[2], ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U'])).$match[3],
            $word,
        );
    }

    /**
     * The word with a written accent on the last vowel of its next-to-last syllable, which an added syllable makes the
     * third from the end (`joven` → `jóven-es`, `liquen` → `líquen-es`).
     */
    private function stressBeforeLast(string $word): string
    {
        return (string) preg_replace_callback(
            '/([aeiou])([^aeiou]+[aeiou]+n)$/i',
            static fn (array $match): string => (strtoupper($match[1]) === $match[1] ? mb_strtoupper(self::STRESS[strtolower($match[1])]) : self::STRESS[$match[1]]).$match[2],
            $word,
        );
    }
}
