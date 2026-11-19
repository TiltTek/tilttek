<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Application;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\EscapeRoomBundle\Application\Contract\TeamNames;

/**
 * The names of an application that has not said how its teams are named: none, so the public leaderboard names each
 * team by its place.
 */
final readonly class UnnamedTeams implements TeamNames
{
    #[Override]
    public function name(ResourceReference $team): ?string
    {
        return null;
    }
}
