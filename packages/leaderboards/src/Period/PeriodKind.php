<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Period;

/**
 * The periods a board ranks over: a calendar day, an ISO week from Monday, a calendar month, quarter or year — each in
 * the board's timezone —, the seasons the application names and dates, ever, or a sliding window of the last days.
 */
enum PeriodKind: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Quarter = 'quarter';
    case Year = 'year';
    case Season = 'season';
    case Ever = 'ever';
    case Window = 'window';
}
