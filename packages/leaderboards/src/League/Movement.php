<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\League;

/**
 * Where a member of a division goes at the end of the week: up a tier, down a tier, or nowhere.
 */
enum Movement: string
{
    case Promoted = 'promoted';
    case Stayed = 'stayed';
    case Relegated = 'relegated';
}
