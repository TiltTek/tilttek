<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets;

/**
 * How the totals of its members make the score of a team.
 */
enum TeamCombine: string
{
    /** The sum of the members' totals. */
    case Sum = 'sum';

    /** The best total of its members. */
    case Best = 'best';
}
