<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\League;

/**
 * How the divisions of a tier are filled each week: in the order the members arrive (`arrival`), or by the volume of
 * their activity the week before (`volume`) — the members of a division then did about as much, so that a newcomer
 * does not meet a member who scores a hundred times more.
 */
enum Formation: string
{
    case Arrival = 'arrival';
    case Volume = 'volume';
}
