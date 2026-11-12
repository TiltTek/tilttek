<?php

declare(strict_types=1);

namespace Tilttek\Generator\Grammar;

use Override;

/**
 * The German rules: the nominative articles by gender and number (`der`, `die`, `das`; `ein`, `eine`; `dieser`,
 * `diese`, `dieses`), the contractions of a preposition with the article (`zu dem` → `zum`, `in das` → `ins`), the
 * plural by the ending of the word with the common irregular ones, and no feminine form of an adjective, which takes
 * its ending from its article and its case.
 */
final readonly class German implements Grammar
{
    private const array CONTRACTED = [
        'an' => ['dem' => 'am', 'das' => 'ans'],
        'bei' => ['dem' => 'beim'],
        'in' => ['dem' => 'im', 'das' => 'ins'],
        'von' => ['dem' => 'vom'],
        'zu' => ['dem' => 'zum', 'der' => 'zur'],
    ];

    private const array IRREGULAR = [
        'apfel' => 'äpfel', 'bär' => 'bären', 'baum' => 'bäume', 'bruder' => 'brüder', 'buch' => 'bücher',
        'dorf' => 'dörfer', 'ei' => 'eier', 'elf' => 'elfen', 'fackel' => 'fackeln', 'feder' => 'federn',
        'frau' => 'frauen', 'fuß' => 'füße', 'gans' => 'gänse', 'gott' => 'götter', 'hand' => 'hände', 'haus' => 'häuser',
        'held' => 'helden', 'insel' => 'inseln', 'kind' => 'kinder', 'kraft' => 'kräfte', 'kugel' => 'kugeln',
        'land' => 'länder', 'mann' => 'männer', 'mauer' => 'mauern', 'maus' => 'mäuse', 'mensch' => 'menschen',
        'mutter' => 'mütter', 'nacht' => 'nächte', 'ohr' => 'ohren', 'prinz' => 'prinzen', 'raum' => 'räume', 'regel' => 'regeln',
        'schatz' => 'schätze', 'schwert' => 'schwerter', 'schwester' => 'schwestern', 'stadt' => 'städte',
        'tochter' => 'töchter', 'traum' => 'träume', 'tür' => 'türen', 'turm' => 'türme', 'uhr' => 'uhren', 'vater' => 'väter',
        'vogel' => 'vögel', 'volk' => 'völker', 'wald' => 'wälder', 'welt' => 'welten', 'wolf' => 'wölfe',
        'zeit' => 'zeiten',
    ];

    #[Override]
    public function elide(string $text): string
    {
        [$words, $gaps] = Words::split($text);

        return Words::glue(...Words::contract($words, $gaps, self::CONTRACTED));
    }

    #[Override]
    public function article(string $word, ArticleKind $kind, Gender $gender, bool $plural): string
    {
        $article = match ($kind) {
            ArticleKind::Definite => $plural ? 'die' : match ($gender) {
                Gender::Masculine => 'der',
                Gender::Feminine => 'die',
                Gender::Neuter => 'das',
            },
            ArticleKind::Indefinite => $plural ? '' : (Gender::Feminine === $gender ? 'eine' : 'ein'),
            ArticleKind::Partitive => '',
            ArticleKind::Demonstrative => $plural ? 'diese' : match ($gender) {
                Gender::Masculine => 'dieser',
                Gender::Feminine => 'diese',
                Gender::Neuter => 'dieses',
            },
        };

        return '' === $article ? $word : $article.' '.$word;
    }

    #[Override]
    public function feminine(string $word): string
    {
        return $word;
    }

    #[Override]
    public function plural(string $word): string
    {
        $lower = mb_strtolower($word);
        $irregular = self::IRREGULAR[$lower] ?? null;

        return match (true) {
            null !== $irregular => Words::sameCase($irregular, $word),
            str_ends_with($lower, 'e') => $word.'n',
            1 === preg_match('/(er|el|en|lein)$/', $lower) => $word,
            1 === preg_match('/[^e]in$/', $lower) => $word.'nen',
            1 === preg_match('/(ung|heit|keit|schaft|ion|tät|ik|ei)$/', $lower) => $word.'en',
            str_ends_with($lower, 'nis'), str_ends_with($lower, 'us') => $word.'se',
            1 === preg_match('/[^a]um$/', $lower) => substr($word, 0, -2).'en',
            1 === preg_match('/[aiouy]$/', $lower) => $word.'s',
            default => $word.'e',
        };
    }

    #[Override]
    public function isPlural(int $count): bool
    {
        return 1 !== abs($count);
    }
}
