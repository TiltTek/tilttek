<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar;

/**
 * A year as an era numbers it — the year 412 after the Founding —, or as the calendar numbers it when it has no
 * era, the era then null.
 */
final readonly class EraYear
{
    public function __construct(public ?Era $era, public int $year)
    {
    }
}
