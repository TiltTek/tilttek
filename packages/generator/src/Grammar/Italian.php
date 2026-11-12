<?php

declare(strict_types=1);

namespace Tilttek\Generator\Grammar;

use Override;

/**
 * The Italian rules: the articles by gender, number and the sound of the next word (`il`, `lo`, `l'`, `la`, `i`, `gli`,
 * `le`; `un`, `uno`, `una`, `un'`), the articulated prepositions (`di il` → `del`, `a lo` → `allo`, `in l'` → `nell'`,
 * `su le` → `sulle`), the partitive (`del`, `della`, `degli`), and the regular feminine and plural with the common
 * irregular words (`uomo` → `uomini`, `uovo` → `uova`, `problema` → `problemi`).
 */
final readonly class Italian implements Grammar
{
    private const array PREPOSITIONS = ['di' => 'de', 'a' => 'a', 'da' => 'da', 'in' => 'ne', 'su' => 'su'];

    private const array ARTICLES = ['il' => 'l', 'lo' => 'llo', 'la' => 'lla', 'i' => 'i', 'gli' => 'gli', 'le' => 'lle'];

    private const string IMPURE = '/^(s[^aeiouàèéìíòóùú]|z|gn|ps|pn|x|y)/iu';

    private const array IRREGULAR = [
        'ala' => 'ali', 'amico' => 'amici', 'antico' => 'antichi', 'arma' => 'armi', 'braccio' => 'braccia',
        'bue' => 'buoi', 'carico' => 'carichi', 'centinaio' => 'centinaia', 'clima' => 'climi', 'dio' => 'dei',
        'dito' => 'dita', 'dramma' => 'drammi', 'enigma' => 'enigmi', 'fico' => 'fichi', 'ginocchio' => 'ginocchia',
        'greco' => 'greci', 'labbro' => 'labbra', 'mano' => 'mani', 'migliaio' => 'migliaia', 'moglie' => 'mogli',
        'nemico' => 'nemici', 'osso' => 'ossa', 'paio' => 'paia', 'papa' => 'papi', 'pianeta' => 'pianeti',
        'poeta' => 'poeti', 'porco' => 'porci', 'problema' => 'problemi', 'profeta' => 'profeti',
        'programma' => 'programmi', 're' => 're', 'sistema' => 'sistemi', 'tema' => 'temi', 'tempio' => 'templi',
        'uomo' => 'uomini', 'uovo' => 'uova', 'zio' => 'zii',
    ];

    #[Override]
    public function elide(string $text): string
    {
        [$words, $gaps] = Words::split($text);

        for ($i = 0; $i < \count($words) - 1; ++$i) {
            $lower = strtolower($words[$i]);
            $next = $words[$i + 1];
            $vowel = $this->startsWithVowel($next);

            if ($vowel && \in_array($lower, ['il', 'lo', 'la', 'una'], true)) {
                [$words, $gaps] = Words::join($words, $gaps, $i, Words::sameCase('una' === $lower ? "un'" : "l'", $words[$i]).$next);
            } elseif ('il' === $lower && $this->startsImpure($next)) {
                $words[$i] = Words::sameCase('lo', $words[$i]);
            } elseif ('un' === $lower && $this->startsImpure($next)) {
                $words[$i] = Words::sameCase('uno', $words[$i]);
            } elseif ('i' === $lower && ($vowel || $this->startsImpure($next))) {
                $words[$i] = Words::sameCase('gli', $words[$i]);
            }
        }

        for ($i = 0; $i < \count($words) - 1; ++$i) {
            $stem = self::PREPOSITIONS[strtolower($words[$i])] ?? null;
            $next = $words[$i + 1];
            $article = strtolower($next);

            if (null !== $stem && isset(self::ARTICLES[$article])) {
                [$words, $gaps] = Words::join($words, $gaps, $i, Words::sameCase($stem.self::ARTICLES[$article], $words[$i]));
            } elseif (null !== $stem && str_starts_with($article, "l'")) {
                [$words, $gaps] = Words::join($words, $gaps, $i, Words::sameCase($stem."ll'", $words[$i]).substr($next, 2));
            }
        }

        return Words::glue($words, $gaps);
    }

    #[Override]
    public function article(string $word, ArticleKind $kind, Gender $gender, bool $plural): string
    {
        $feminine = Gender::Feminine === $gender;
        $article = match ($kind) {
            ArticleKind::Definite => $plural ? ($feminine ? 'le' : 'i') : ($feminine ? 'la' : 'il'),
            ArticleKind::Indefinite => $plural ? ($feminine ? 'di le' : 'di i') : ($feminine ? 'una' : 'un'),
            ArticleKind::Partitive => 'di '.($plural ? ($feminine ? 'le' : 'i') : ($feminine ? 'la' : 'il')),
            ArticleKind::Demonstrative => $plural ? ($feminine ? 'queste' : 'questi') : ($feminine ? 'questa' : 'questo'),
        };

        return $this->elide($article.' '.$word);
    }

    #[Override]
    public function feminine(string $word): string
    {
        return match (true) {
            str_ends_with($word, 'tore') => substr($word, 0, -3).'rice',
            str_ends_with($word, 'o') => substr($word, 0, -1).'a',
            default => $word,
        };
    }

    #[Override]
    public function plural(string $word): string
    {
        $lower = strtolower($word);
        $irregular = self::IRREGULAR[$lower] ?? null;

        return match (true) {
            null !== $irregular => Words::sameCase($irregular, $word),
            1 === preg_match('/([^aeiou]|i|ie)$/', $lower) => $word,
            str_ends_with($lower, 'ico') => substr($word, 0, -1).'i',
            str_ends_with($lower, 'co'), str_ends_with($lower, 'go') => substr($word, 0, -1).'hi',
            str_ends_with($lower, 'io') => substr($word, 0, -1),
            str_ends_with($lower, 'ca'), str_ends_with($lower, 'ga') => substr($word, 0, -1).'he',
            1 === preg_match('/[aeiou][cg]ia$/', $lower) => substr($word, 0, -1).'e',
            str_ends_with($lower, 'cia'), str_ends_with($lower, 'gia') => substr($word, 0, -2).'e',
            str_ends_with($lower, 'a') => substr($word, 0, -1).'e',
            default => substr($word, 0, -1).'i',
        };
    }

    #[Override]
    public function isPlural(int $count): bool
    {
        return 1 !== abs($count);
    }

    private function startsWithVowel(string $word): bool
    {
        return 1 === preg_match('/^[aeiouàèéìíòóùúh]/iu', $word);
    }

    private function startsImpure(string $word): bool
    {
        return 1 === preg_match(self::IMPURE, $word);
    }
}
