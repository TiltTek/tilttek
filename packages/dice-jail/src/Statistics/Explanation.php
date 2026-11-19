<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Statistics;

/**
 * The verdict of a test written for the table, in plain words, in English or in French.
 *
 * Every sentence says how often a fair die would roll like this, never that the die is loaded or cursed: the most
 * unusual verdict asks for a look at the die, or for new rolls, and the rolls of a digital die are said to come from a
 * generator that serves every die alike.
 */
final readonly class Explanation
{
    private const array SENTENCES = [
        'en' => [
            'not_enough' => 'Not enough rolls to judge this die yet: %rolls% recorded, %minimum% needed. Until then, a streak is only chance.',
            'nothing' => 'Nothing unusual in %rolls% rolls: a fair die spreads its faces at least this unevenly %chance% of the time (%p%).',
            'somewhat' => 'Somewhat unusual in %rolls% rolls: a fair die spreads its faces this unevenly about once in %series% series (%p%). It proves nothing; roll it again and judge the new rolls alone.',
            'very' => 'Very unusual in %rolls% rolls: a fair die spreads its faces this unevenly fewer than once in %series% series (%p%).',
            'very_physical' => 'It is a reason to look at the die — its edges, its balance, how it is thrown — not proof that it is loaded.',
            'very_digital' => 'Test new rolls before suspecting anything: chance remains the likeliest cause.',
            'mean' => 'Average roll: %observed%, against %expected% for a fair die.',
            'digital' => 'These rolls come from a random generator that serves every die alike: no digital die is luckier than another, and the jail is only a game.',
        ],
        'fr' => [
            'not_enough' => "Pas encore assez de lancers pour juger ce dé\u{a0}: %rolls% notés, %minimum% nécessaires. D’ici là, une série n’est que le hasard.",
            'nothing' => "Rien d’inhabituel sur %rolls% lancers\u{a0}: un dé équilibré répartit ses faces au moins aussi inégalement %chance% du temps (%p%).",
            'somewhat' => "Un peu inhabituel sur %rolls% lancers\u{a0}: un dé équilibré répartit ses faces aussi inégalement environ une série sur %series% (%p%). Cela ne prouve rien\u{202f}; relancez-le et jugez les nouveaux lancers seuls.",
            'very' => "Très inhabituel sur %rolls% lancers\u{a0}: un dé équilibré répartit ses faces aussi inégalement moins d’une série sur %series% (%p%).",
            'very_physical' => 'C’est une raison d’examiner le dé — ses arêtes, son équilibre, la façon de le lancer —, pas la preuve qu’il est pipé.',
            'very_digital' => "Testez de nouveaux lancers avant de soupçonner quoi que ce soit\u{a0}: le hasard reste la cause la plus probable.",
            'mean' => "Moyenne des lancers\u{a0}: %observed%, contre %expected% pour un dé équilibré.",
            'digital' => "Ces lancers viennent d’un générateur aléatoire qui sert tous les dés de la même façon\u{a0}: aucun dé numérique n’est plus chanceux qu’un autre, et la prison n’est qu’un jeu.",
        ],
    ];

    /** Below it, a p-value is written as less than it. */
    private const float SMALLEST_P = 0.0001;

    public function explain(FitResult $result, Language $language = Language::English, Origin $origin = Origin::Physical): string
    {
        $words = self::SENTENCES[$language->value];
        $sentences = [match ($result->verdict) {
            Verdict::NotEnoughRolls => strtr($words['not_enough'], [
                '%rolls%' => $this->integer($result->rolls, $language),
                '%minimum%' => $this->integer($result->minimumRolls, $language),
            ]),
            Verdict::NothingUnusual => strtr($words['nothing'], $this->numbers($result, $language) + [
                '%chance%' => $this->percent((float) $result->pValue, $language),
            ]),
            Verdict::SomewhatUnusual => strtr($words['somewhat'], $this->numbers($result, $language) + [
                '%series%' => $this->integer((int) round(1 / (float) $result->pValue), $language),
            ]),
            Verdict::VeryUnusual => strtr($words['very'], $this->numbers($result, $language) + [
                '%series%' => $this->integer((int) round(1 / Verdict::VERY_UNUSUAL_BELOW), $language),
            ]).' '.$words[Origin::Physical === $origin ? 'very_physical' : 'very_digital'],
        }];

        if ($result->rolls > 0) {
            $sentences[] = strtr($words['mean'], [
                '%observed%' => $this->decimal($result->observedMean, 2, $language),
                '%expected%' => $this->decimal($result->expectedMean, 2, $language),
            ]);
        }

        if (Origin::Digital === $origin) {
            $sentences[] = $words['digital'];
        }

        return implode(' ', $sentences);
    }

    /**
     * `p = 0.0407`, `p < 0.0001`, in the language's way of writing numbers.
     */
    public function pValue(float $pValue, Language $language = Language::English): string
    {
        return $pValue < self::SMALLEST_P
            ? 'p < '.$this->decimal(self::SMALLEST_P, 4, $language)
            : 'p = '.$this->decimal($pValue, 4, $language);
    }

    /**
     * @return array<string, string>
     */
    private function numbers(FitResult $result, Language $language): array
    {
        return [
            '%rolls%' => $this->integer($result->rolls, $language),
            '%p%' => $this->pValue((float) $result->pValue, $language),
        ];
    }

    private function percent(float $share, Language $language): string
    {
        $percent = $share * 100;
        $number = $this->decimal($percent, $percent < 10 ? 1 : 0, $language);

        return Language::French === $language ? $number."\u{a0}%" : $number.'%';
    }

    private function integer(int $value, Language $language): string
    {
        return $this->decimal($value, 0, $language);
    }

    private function decimal(float $value, int $decimals, Language $language): string
    {
        return Language::French === $language
            ? number_format($value, $decimals, ',', "\u{202f}")
            : number_format($value, $decimals);
    }
}
