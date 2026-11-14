<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Team;

/**
 * How the scores of a team's players make the team's score.
 */
enum TeamScoring: string
{
    /** The sum of its players' scores and times: a larger team may score more. */
    case Total = 'total';

    /**
     * The mean of its players' scores and times, rounded to the nearest whole number, half up: teams of different
     * sizes compete on an even footing. A team without players scores 0.
     */
    case Average = 'average';
}
