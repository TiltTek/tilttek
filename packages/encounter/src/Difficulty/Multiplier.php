<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Difficulty;

use Tilttek\Encounter\Exception\InvalidEncounter;

/**
 * What the cost of the adversaries is multiplied by from a number of them on, in percent: `new Multiplier(3, 200)` doubles
 * the cost of three adversaries or more.
 */
final readonly class Multiplier
{
    /** The highest percentage. */
    public const int MAX_PERCENT = 1000;

    /**
     * @param int $from    the number of adversaries, every copy counted, from which it holds; 1 or more
     * @param int $percent 1 to 1,000: 100 keeps the cost, 150 adds half
     *
     * @throws InvalidEncounter when a value is out of its bounds
     */
    public function __construct(public int $from, public int $percent)
    {
        if ($from < 1) {
            throw InvalidEncounter::because('A multiplier holds from 1 adversary or more.');
        }

        if ($percent < 1 || $percent > self::MAX_PERCENT) {
            throw InvalidEncounter::because(\sprintf('A multiplier is 1 to %d percent.', self::MAX_PERCENT));
        }
    }
}
