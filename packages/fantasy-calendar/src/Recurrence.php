<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar;

/**
 * How often a reminder comes back: never, every year on its month and day, or every month on its day. A year or a
 * month without that day — a leap day, a short month — skips it.
 */
enum Recurrence: string
{
    case Once = 'once';
    case Yearly = 'yearly';
    case Monthly = 'monthly';
}
