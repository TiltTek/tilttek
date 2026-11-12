<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Computation;

use Tilttek\DiceProbability\Event\Event;
use Tilttek\DiceProbability\Event\Faces;
use Tilttek\DiceProbability\Exact\Natural;

/**
 * The weight of an event over the sorted multisets of a set of dice: each way the dice can fall without their order —
 * 126 for four six-sided dice rather than 1 296 rolls —, weighed by the number of rolls that give it.
 *
 * @internal
 */
final class Multisets
{
    private function __construct()
    {
    }

    /**
     * The weight of the multisets that match, over the die's total raised to the number of dice.
     *
     * @throws TooLarge when the multisets go past the budget
     */
    public static function weigh(Weights $die, int $count, Event $event, Budget $budget): Natural
    {
        $values = array_keys($die->weights);
        $powers = [];

        foreach ($die->weights as $value => $weight) {
            $powers[$value] = [Natural::one()];

            for ($dice = 1; $dice <= $count; ++$dice) {
                $budget->spendOn(1, $powers[$value][$dice - 1], $weight);
                $powers[$value][$dice] = $powers[$value][$dice - 1]->multiply($weight);
            }
        }

        return self::walk($values, 0, $count, [], Natural::one(), $powers, self::binomials($count), $event, $budget);
    }

    /**
     * @param list<int>                       $values
     * @param array<int, int>                 $counts
     * @param array<int, array<int, Natural>> $powers
     * @param array<int, array<int, Natural>> $binomials
     */
    private static function walk(array $values, int $index, int $left, array $counts, Natural $ways, array $powers, array $binomials, Event $event, Budget $budget): Natural
    {
        $value = $values[$index];

        if ($index === \count($values) - 1) {
            // The faces of the multiset, one count per value, judged by the event.
            $budget->spend(\count($values));
            $budget->spendOn(1, $ways, $powers[$value][$left]);
            $counts[$value] = $left;

            return $event->matches(new Faces($counts)) ? $ways->multiply($powers[$value][$left]) : Natural::zero();
        }

        $weight = Natural::zero();

        for ($dice = 0; $dice <= $left; ++$dice) {
            $budget->spendOn(1, $ways, $powers[$value][$dice]);
            $counts[$value] = $dice;
            $weight = $weight->add(self::walk($values, $index + 1, $left - $dice, $counts, $ways->multiply($binomials[$left][$dice])->multiply($powers[$value][$dice]), $powers, $binomials, $event, $budget));
        }

        return $weight;
    }

    /**
     * The binomial coefficients up to a number of dice, by Pascal's triangle.
     *
     * @return array<int, array<int, Natural>>
     */
    public static function binomials(int $count): array
    {
        $rows = [[Natural::one()]];

        for ($row = 1; $row <= $count; ++$row) {
            $rows[$row] = [Natural::one()];

            for ($column = 1; $column < $row; ++$column) {
                $rows[$row][$column] = $rows[$row - 1][$column - 1]->add($rows[$row - 1][$column]);
            }

            $rows[$row][$row] = Natural::one();
        }

        return $rows;
    }
}
