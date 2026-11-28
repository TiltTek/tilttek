<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar;

use Tilttek\FantasyCalendar\Exception\InvalidCalendar;

/**
 * One cycle of a leap rule: every so many years, from an offset, a year is leap — or, for an exception, is not.
 */
final readonly class LeapCycle
{
    /**
     * @throws InvalidCalendar when the interval is out of its bounds
     */
    public function __construct(public int $every, public int $offset = 0, public bool $leap = true)
    {
        Limits::between($every, 1, Limits::LEAP_PERIOD, 'The interval of a leap cycle');
        Limits::between($offset, 0, $every - 1, 'The offset of a leap cycle');
    }

    public function matches(int $year): bool
    {
        return 0 === Limits::mod($year - $this->offset, $this->every);
    }
}
