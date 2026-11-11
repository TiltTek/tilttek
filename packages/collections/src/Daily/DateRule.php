<?php

declare(strict_types=1);

namespace Tilttek\Collections\Daily;

use DateTimeImmutable;

/**
 * A rule of the calendar of the item of the day: for a day, at midnight in the calendar's timezone, the code of the
 * item it puts forward, or null when it says nothing of that day. Fixed dates are built in; computed dates — a week
 * from Easter, the fourth Thursday of November — come from a rule of the application's own.
 */
interface DateRule
{
    public function itemOn(DateTimeImmutable $day): ?string;
}
