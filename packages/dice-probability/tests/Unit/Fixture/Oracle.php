<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Tests\Unit\Fixture;

use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Dice\Expression\Dice;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Expression\ValueList;
use Tilttek\Dice\Rolling\Roller;

/**
 * The distribution of a set of dice by brute force: every roll of the faces given, each judged by the library's own
 * roller as a list of values with the set's modifiers.
 */
final class Oracle
{
    /**
     * @param list<int> $faces the faces of one die, a face given twice weighing twice
     *
     * @return array<int, string> the number of rolls by value
     */
    public static function weights(Dice $dice, array $faces): array
    {
        $roller = new Roller();
        $randomizer = new Randomizer(new Xoshiro256StarStar(0));
        $weights = [];

        foreach (self::rolls($faces, $dice->count) as $roll) {
            $value = $roller->roll(new Pool(new ValueList($roll, $dice->modifiers)), $randomizer)->results[0]->result->value();
            $weights[$value] = ($weights[$value] ?? 0) + 1;
        }

        ksort($weights);

        return array_map(strval(...), $weights);
    }

    /**
     * @param list<int> $faces
     *
     * @return iterable<list<int>>
     */
    private static function rolls(array $faces, int $count): iterable
    {
        if (0 === $count) {
            yield [];

            return;
        }

        foreach (self::rolls($faces, $count - 1) as $roll) {
            foreach ($faces as $face) {
                yield [...$roll, $face];
            }
        }
    }
}
