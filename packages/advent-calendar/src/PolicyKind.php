<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendar;

/**
 * How long a box stays openable after its day has come.
 */
enum PolicyKind: string
{
    /** Until the calendar closes: a member who missed a day opens its box later, without losing it. */
    case UntilClosing = 'until_closing';

    /** On its day only, in the calendar's timezone. */
    case SameDay = 'same_day';

    /** On its day and a number of days after, never past the closing. */
    case CatchUp = 'catch_up';
}
