<?php

declare(strict_types=1);

namespace Tilttek\Weather;

use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Exception\DiceException;
use Tilttek\Weather\Exception\InvalidClimate;

/**
 * A climate zone — temperate, arid — with its labels, its seasons and what each brings, how strongly a condition stays
 * from one day to the next, and the roll of `tilttek/dice` that changes the temperature each day.
 */
final readonly class Zone
{
    /**
     * The most seasons of a zone.
     */
    public const int SEASONS = 16;

    /**
     * The strongest persistence: how many times more likely a condition is to stay than to come.
     */
    public const int PERSISTENCE = 20;

    /**
     * @var array<string, string> by language
     */
    public array $labels;

    /**
     * @var non-empty-array<string, SeasonalClimate> by season, in their order
     */
    public array $seasons;

    /**
     * @param array<array-key, mixed> $labels      by language
     * @param list<SeasonalClimate>   $seasons     what each season brings, in the order of the year
     * @param int                     $persistence how many times more weight a condition has to stay than to come
     * @param string                  $change      the roll of `tilttek/dice` added to the temperature each day, in degrees
     *
     * @throws InvalidClimate
     */
    public function __construct(public string $id, array $labels, array $seasons, public int $persistence = 3, public string $change = '1d5-3')
    {
        $place = 'zones.'.Rules::identifier('zones', $id);
        $this->labels = Rules::labels($place.'.labels', $labels);
        Rules::between($place.'.persistence', $persistence, 1, self::PERSISTENCE);
        $this->roll($place.'.change', $change);

        if ([] === $seasons || \count($seasons) > self::SEASONS) {
            throw InvalidClimate::at($place.'.seasons', \sprintf('a zone has 1 to %d seasons', self::SEASONS));
        }

        $bySeason = [];

        foreach ($seasons as $climate) {
            if (isset($bySeason[$climate->season])) {
                throw InvalidClimate::at($place.'.seasons.'.$climate->season, 'a season comes once in a zone');
            }

            $bySeason[Rules::identifier($place.'.seasons', $climate->season)] = $climate;
        }

        $this->seasons = $bySeason;
    }

    /**
     * The first season of the zone, which a state without a season starts in.
     */
    public function firstSeason(): string
    {
        return array_key_first($this->seasons);
    }

    /**
     * @throws InvalidClimate when the expression does not parse or has no whole total
     */
    private function roll(string $place, string $change): void
    {
        $dice = new DiceRoller();

        try {
            $totals = $dice->roll($dice->parse($change), new Randomizer(new Xoshiro256StarStar(str_repeat('a', 32))))->totals();
        } catch (DiceException $diceException) {
            throw InvalidClimate::at($place, \sprintf('"%s" is not a roll of tilttek/dice: %s', $change, $diceException->getMessage()));
        }

        if (1 !== \count($totals) || null === $totals[0]) {
            throw InvalidClimate::at($place, \sprintf('"%s" does not roll one whole total', $change));
        }
    }
}
