<?php

declare(strict_types=1);

namespace Tilttek\Generator\Grammar;

use Override;

/**
 * The Portuguese rules: the articles by gender and number (`o`, `a`, `os`, `as`; `um`, `uma`, `uns`, `umas`; `este`,
 * `esta`), the contractions of `de`, `em`, `a` and `por` with the article and the demonstrative (`de o` → `do`,
 * `em a` → `na`, `a a` → `à`, `por os` → `pelos`, `de este` → `deste`), and the regular feminine and plural with the
 * common irregular words (`alemão` → `alemães`, `mão` → `mãos`). The count 0 takes the singular, as in Brazil, unless
 * the grammar is built for Portugal, where 1 alone takes it.
 */
final readonly class Portuguese implements Grammar
{
    private const array ARTICLES = ['o', 'a', 'os', 'as'];

    private const array DEMONSTRATIVES = [
        'este', 'esta', 'estes', 'estas', 'esse', 'essa', 'esses', 'essas', 'aquele', 'aquela', 'aqueles', 'aquelas',
        'isto', 'isso', 'aquilo',
    ];

    private const array FEMININE = [
        'bonachão' => 'bonachona', 'chorão' => 'chorona', 'comilão' => 'comilona', 'judeu' => 'judia',
        'ladrão' => 'ladra', 'leão' => 'leoa', 'valentão' => 'valentona',
    ];

    private const array INVARIABLE_FEMININE = [
        'anterior', 'cortês', 'descortês', 'exterior', 'hindu', 'inferior', 'interior', 'maior', 'melhor', 'menor',
        'pior', 'posterior', 'superior', 'ulterior',
    ];

    private const array IRREGULAR = [
        'alemão' => 'alemães', 'bênção' => 'bênçãos', 'cão' => 'cães', 'capitão' => 'capitães', 'caráter' => 'caracteres',
        'catalão' => 'catalães', 'chão' => 'chãos', 'charlatão' => 'charlatães', 'cidadão' => 'cidadãos',
        'cônsul' => 'cônsules', 'cristão' => 'cristãos', 'escrivão' => 'escrivães', 'grão' => 'grãos',
        'irmão' => 'irmãos', 'júnior' => 'juniores', 'mal' => 'males', 'mão' => 'mãos', 'órfão' => 'órfãos',
        'órgão' => 'órgãos', 'pagão' => 'pagãos', 'pão' => 'pães', 'sacristão' => 'sacristães', 'sênior' => 'seniores',
        'tabelião' => 'tabeliães',
    ];

    private const string VOWELS = 'aeiouáéíóúâêôãõ';

    private const string ACCENTS = 'á|é|í|ó|ú|â|ê|ô|ã|õ';

    private const array OPEN = ['e' => 'é', 'o' => 'ó', 'E' => 'É', 'O' => 'Ó'];

    /**
     * @var array<string, array<string, string>>
     */
    private array $contracted;

    /**
     * @param bool $zeroIsSingular whether the count 0 takes the singular, as in Brazil, or the plural, as in Portugal
     */
    public function __construct(
        private bool $zeroIsSingular = true,
    ) {
        $contracted = ['a' => ['o' => 'ao', 'os' => 'aos', 'a' => 'à', 'as' => 'às']];

        foreach (self::ARTICLES as $article) {
            $contracted['de'][$article] = 'd'.$article;
            $contracted['em'][$article] = 'n'.$article;
            $contracted['por'][$article] = 'pel'.$article;
        }

        foreach (self::DEMONSTRATIVES as $demonstrative) {
            $contracted['de'][$demonstrative] = 'd'.$demonstrative;
            $contracted['em'][$demonstrative] = 'n'.$demonstrative;
        }

        foreach (['aquele', 'aquela', 'aqueles', 'aquelas', 'aquilo'] as $demonstrative) {
            $contracted['a'][$demonstrative] = 'à'.substr($demonstrative, 1);
        }

        $this->contracted = $contracted;
    }

    #[Override]
    public function elide(string $text): string
    {
        [$words, $gaps] = Words::split($text);

        return Words::glue(...Words::contract($words, $gaps, $this->contracted));
    }

    #[Override]
    public function article(string $word, ArticleKind $kind, Gender $gender, bool $plural): string
    {
        $feminine = Gender::Feminine === $gender;
        $article = match ($kind) {
            ArticleKind::Definite => ($feminine ? 'a' : 'o').($plural ? 's' : ''),
            ArticleKind::Indefinite => $plural ? ($feminine ? 'umas' : 'uns') : ($feminine ? 'uma' : 'um'),
            ArticleKind::Partitive => '',
            ArticleKind::Demonstrative => ($feminine ? 'esta' : 'este').($plural ? 's' : ''),
        };

        return '' === $article ? $word : $article.' '.$word;
    }

    #[Override]
    public function feminine(string $word): string
    {
        $lower = mb_strtolower($word);
        $irregular = self::FEMININE[$lower] ?? null;

        return match (true) {
            null !== $irregular => Words::sameCase($irregular, $word),
            \in_array($lower, self::INVARIABLE_FEMININE, true) => $word,
            str_ends_with($lower, 'ão') => substr($word, 0, -\strlen('ão')).'ã',
            str_ends_with($lower, 'ês') => substr($word, 0, -\strlen('ês')).'esa',
            str_ends_with($lower, 'eu') => substr($word, 0, -1).'ia',
            str_ends_with($lower, 'o') => substr($word, 0, -1).'a',
            str_ends_with($lower, 'u'), str_ends_with($lower, 'or') => $word.'a',
            default => $word,
        };
    }

    #[Override]
    public function plural(string $word): string
    {
        $lower = mb_strtolower($word);
        $irregular = self::IRREGULAR[$lower] ?? null;
        $accented = 1 === preg_match('/'.self::ACCENTS.'/', $lower);

        return match (true) {
            null !== $irregular => Words::sameCase($irregular, $word),
            str_ends_with($lower, 'ão') => substr($word, 0, -\strlen('ão')).'ões',
            str_ends_with($lower, 'm') => substr($word, 0, -1).'ns',
            str_ends_with($lower, 'r'), str_ends_with($lower, 'z') => $word.'es',
            1 === preg_match('/(á|é|í|ó|ú|â|ê|ô)s$/', $lower) => $this->unstressLast($word).'es',
            str_ends_with($lower, 's') => preg_match_all('/['.self::VOWELS.']+/u', $lower) > 1 ? $word : $word.'es',
            str_ends_with($lower, 'al'), str_ends_with($lower, 'ul') => substr($word, 0, -1).'is',
            str_ends_with($lower, 'el'), str_ends_with($lower, 'ol') => substr($word, 0, -2).($accented ? $word[-2] : strtr($word[-2], self::OPEN)).'is',
            str_ends_with($lower, 'il') => substr($word, 0, -2).($accented ? 'eis' : 'is'),
            str_ends_with($lower, 'x') => $word,
            default => $word.'s',
        };
    }

    #[Override]
    public function isPlural(int $count): bool
    {
        return $this->zeroIsSingular ? abs($count) >= 2 : 1 !== abs($count);
    }

    /**
     * The word without the written accent of its last syllable, which an added syllable makes useless — except on an
     * i or a u that stands apart from the vowel before it (`país` → `países`).
     */
    private function unstressLast(string $word): string
    {
        return (string) preg_replace_callback(
            '/(['.self::VOWELS.']?)([áéíóúâêô])(s)$/iu',
            static fn (array $match): string => $match[1].('' !== $match[1] && 1 === preg_match('/[íú]/iu', $match[2]) ? $match[2] : strtr($match[2], ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'â' => 'a', 'ê' => 'e', 'ô' => 'o', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Â' => 'A', 'Ê' => 'E', 'Ô' => 'O'])).$match[3],
            $word,
        );
    }
}
