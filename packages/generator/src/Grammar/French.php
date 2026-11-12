<?php

declare(strict_types=1);

namespace Tilttek\Generator\Grammar;

use Override;

/**
 * The French rules: elision before a vowel or a mute h (`le arbre` → `l'arbre`, `que il` → `qu'il`, `si il` → `s'il`),
 * the forms before a vowel (`ce` → `cet`, `ma` → `mon`), the contractions (`de le` → `du`, `à les` → `aux`), the
 * articles by gender and number, and the regular feminine and plural. A word whose h is aspirated keeps its article
 * whole (`le héros`); the common ones are built in and an application adds its own.
 */
final readonly class French implements Grammar
{
    private const array ELIDED = [
        'le' => 'l', 'la' => 'l', 'de' => 'd', 'je' => 'j', 'me' => 'm', 'te' => 't', 'se' => 's', 'ne' => 'n',
        'que' => 'qu', 'jusque' => 'jusqu', 'lorsque' => 'lorsqu', 'puisque' => 'puisqu', 'quoique' => 'quoiqu',
    ];

    private const array BEFORE_VOWEL = ['ce' => 'cet', 'ma' => 'mon', 'ta' => 'ton', 'sa' => 'son'];

    private const array CONTRACTED = ['de' => ['le' => 'du', 'les' => 'des'], 'à' => ['le' => 'au', 'les' => 'aux']];

    private const string VOWELS = 'aeiouàâäéèêëîïôöùûüœæ';

    private const array ASPIRATED = [
        'hache', 'haie', 'haillon', 'haine', 'hall', 'halle', 'hallebarde', 'halte', 'hamac', 'hameau', 'hanche',
        'handicap', 'hangar', 'hanneton', 'harde', 'hareng', 'hargne', 'haricot', 'harnais', 'harpe', 'harpie', 'hasard',
        'hâte', 'haubert', 'haut', 'hautbois', 'hauteur', 'havre', 'hennin', 'héraut', 'hérisson', 'hernie', 'héron',
        'héros', 'hêtre', 'heurt', 'hibou', 'hideux', 'hiérarchie', 'homard', 'honte', 'hoquet', 'horde', 'hors',
        'hotte', 'houblon', 'houle', 'houx', 'huit', 'huitième', 'hublot', 'huée', 'hurlement', 'hussard', 'hutte',
        'onze', 'onzième', 'oui', 'ouistiti', 'yacht',
    ];

    private const array FEMININE_EUR_E = ['meilleur', 'majeur', 'mineur', 'intérieur', 'extérieur', 'supérieur', 'inférieur', 'antérieur', 'postérieur', 'ultérieur'];

    private const array PLURAL_AL_S = ['bal', 'banal', 'carnaval', 'chacal', 'fatal', 'festival', 'final', 'natal', 'naval', 'récital', 'régal'];

    private const array PLURAL_AIL_AUX = ['bail', 'corail', 'émail', 'soupirail', 'travail', 'vantail', 'vitrail'];

    private const array PLURAL_EU_S = ['bleu', 'pneu', 'émeu', 'landau', 'sarrau'];

    private const array PLURAL_OU_X = ['bijou', 'caillou', 'chou', 'genou', 'hibou', 'joujou', 'pou'];

    /**
     * @var list<string>
     */
    private array $aspirated;

    /**
     * @param list<string> $aspirated more words whose h is aspirated, or whose vowel takes no elision
     */
    public function __construct(array $aspirated = [])
    {
        $this->aspirated = [...self::ASPIRATED, ...array_map(mb_strtolower(...), $aspirated)];
    }

    #[Override]
    public function elide(string $text): string
    {
        [$words, $gaps] = Words::split($text);

        for ($i = 0; $i < \count($words) - 1; ++$i) {
            $lower = strtolower($words[$i]);
            $next = $words[$i + 1];

            if (!$this->startsWithVowelSound($next)) {
                continue;
            }

            if (isset(self::ELIDED[$lower]) || ('si' === $lower && \in_array(strtolower($next), ['il', 'ils'], true))) {
                [$words, $gaps] = Words::join($words, $gaps, $i, Words::sameCase(self::ELIDED[$lower] ?? 's', $words[$i])."'".$next);
            } elseif (isset(self::BEFORE_VOWEL[$lower])) {
                $words[$i] = Words::sameCase(self::BEFORE_VOWEL[$lower], $words[$i]);
            }
        }

        for ($i = 0; $i < \count($words) - 1; ++$i) {
            $contracted = self::CONTRACTED[mb_strtolower($words[$i])][strtolower($words[$i + 1])] ?? null;

            if (null !== $contracted) {
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
            ArticleKind::Definite => $plural ? 'les' : ($feminine ? 'la' : 'le'),
            ArticleKind::Indefinite => $plural ? 'des' : ($feminine ? 'une' : 'un'),
            ArticleKind::Partitive => $plural ? 'des' : ($feminine ? 'de la' : 'de le'),
            ArticleKind::Demonstrative => $plural ? 'ces' : ($feminine ? 'cette' : 'ce'),
        };

        return $this->elide($article.' '.$word);
    }

    #[Override]
    public function feminine(string $word): string
    {
        return match (true) {
            str_ends_with($word, 'e') => $word,
            str_ends_with($word, 'eux') => substr($word, 0, -1).'se',
            str_ends_with($word, 'eur') => \in_array(strtolower($word), self::FEMININE_EUR_E, true) ? $word.'e' : substr($word, 0, -1).'se',
            str_ends_with($word, 'if') => substr($word, 0, -1).'ve',
            str_ends_with($word, 'er') => substr($word, 0, -2).'ère',
            str_ends_with($word, 'el'), str_ends_with($word, 'eil') => $word.'le',
            str_ends_with($word, 'en'), str_ends_with($word, 'on') => $word.'ne',
            str_ends_with($word, 'et') => $word.'te',
            default => $word.'e',
        };
    }

    #[Override]
    public function plural(string $word): string
    {
        $lower = mb_strtolower($word);

        return match (true) {
            str_ends_with($word, 's'), str_ends_with($word, 'x'), str_ends_with($word, 'z') => $word,
            str_ends_with($word, 'al') => \in_array($lower, self::PLURAL_AL_S, true) ? $word.'s' : substr($word, 0, -1).'ux',
            str_ends_with($word, 'ail') => \in_array($lower, self::PLURAL_AIL_AUX, true) ? substr($word, 0, -2).'ux' : $word.'s',
            str_ends_with($word, 'au'), str_ends_with($word, 'eu') => \in_array($lower, self::PLURAL_EU_S, true) ? $word.'s' : $word.'x',
            str_ends_with($word, 'ou') => \in_array($lower, self::PLURAL_OU_X, true) ? $word.'x' : $word.'s',
            default => $word.'s',
        };
    }

    #[Override]
    public function isPlural(int $count): bool
    {
        return abs($count) >= 2;
    }

    private function startsWithVowelSound(string $word): bool
    {
        if ('' === $word) {
            return false;
        }

        $lower = mb_strtolower($word);
        $bare = rtrim($lower, '.,;:!?»"\')');

        if (\in_array($bare, $this->aspirated, true) || \in_array(substr($bare, 0, -1), $this->aspirated, true)) {
            return false;
        }

        $first = mb_substr($lower, 0, 1);

        return 'h' === $first || str_contains(self::VOWELS, $first);
    }
}
