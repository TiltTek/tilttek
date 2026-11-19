<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Statistics;

use Tilttek\Dice\Result\RolledDie;
use Tilttek\DiceJail\Exception\InvalidRolls;

/**
 * How many times each face of one die came up.
 */
final readonly class Tally
{
    /** The most rolls a tally holds: past it, the statistics would say no more than they do at it. */
    public const int MAX_ROLLS = 1_000_000;

    /**
     * @var array<int, int> by face, in ascending order
     */
    public array $counts;

    public int $total;

    /**
     * @param array<int, int> $counts by face; the faces that never came up may be left out
     *
     * @throws InvalidRolls when the tally holds more than `MAX_ROLLS` rolls
     */
    private function __construct(array $counts)
    {
        ksort($counts);
        $this->counts = $counts;
        $this->total = array_sum($counts);
    }

    /**
     * The faces as they came up, one value per roll.
     *
     * @param iterable<int> $faces
     *
     * @throws InvalidRolls when there are more than `MAX_ROLLS` of them
     */
    public static function of(iterable $faces): self
    {
        $counts = [];
        $total = 0;

        foreach ($faces as $face) {
            if (++$total > self::MAX_ROLLS) {
                throw InvalidRolls::tooMany(self::MAX_ROLLS);
            }

            $counts[$face] = ($counts[$face] ?? 0) + 1;
        }

        return new self($counts);
    }

    /**
     * The dice of rolls made with `tilttek/dice`: every roll each die is made of, rerolled and exploded ones included,
     * since each is a throw of the same die.
     *
     * @param iterable<RolledDie> $dice
     *
     * @throws InvalidRolls when there are more than `MAX_ROLLS` rolls
     */
    public static function ofRolledDice(iterable $dice): self
    {
        return self::of((static function () use ($dice): iterable {
            foreach ($dice as $die) {
                yield from self::rollsOf($die);
            }
        })());
    }

    /**
     * @return non-empty-list<int>
     */
    private static function rollsOf(RolledDie $die): array
    {
        return $die->rolls;
    }

    /** How many times the face came up. */
    public function count(int $face): int
    {
        return $this->counts[$face] ?? 0;
    }
}
