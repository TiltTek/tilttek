<?php

declare(strict_types=1);

namespace Tilttek\Generator\Grammar;

/**
 * The rules of one language that the generator's filters apply: elision and contraction over a whole text, articles,
 * the feminine and the plural of a word, and which counts take the plural.
 */
interface Grammar
{
    /**
     * The mark written before a word to keep it as written: `elide` makes no elision, contraction or change of form
     * on the pair it starts or ends, then removes the mark — `de ^le roi` gives `de le roi`, `zu ^der` gives `zu der`.
     */
    public const string KEEP = '^';

    /**
     * The text with its elisions and contractions made: `le arbre` becomes `l'arbre` in French, `a apple` becomes
     * `an apple` in English. A word that starts with `KEEP` is left as written, and so is the word before it; the
     * mark is removed.
     */
    public function elide(string $text): string;

    /**
     * The word, already in the form wanted, with its article before it.
     */
    public function article(string $word, ArticleKind $kind, Gender $gender, bool $plural): string;

    /**
     * The feminine form of a word given in the masculine, by the language's regular rules.
     */
    public function feminine(string $word): string;

    /**
     * The plural form of a word given in the singular, by the language's regular rules.
     */
    public function plural(string $word): string;

    /**
     * Whether this count takes the plural.
     */
    public function isPlural(int $count): bool;
}
