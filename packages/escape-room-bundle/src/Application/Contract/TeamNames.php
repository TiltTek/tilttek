<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Application\Contract;

use Phil\Resource\ResourceReference;

/**
 * The names the public leaderboard shows for the teams, from the application's own teams: a name the team chose,
 * never an email address. Without one, the leaderboard names a team by its place.
 */
interface TeamNames
{
    public function name(ResourceReference $team): ?string;
}
