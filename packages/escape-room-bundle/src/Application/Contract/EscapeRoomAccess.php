<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Application\Contract;

use Phil\Resource\ResourceReference;
use Tilttek\EscapeRoomBundle\Application\StoredGame;

/**
 * Who the current viewer is to a game: the application answers, from its own members, staff and teams. Without an
 * answer, the console, the hint screen and the consent are refused.
 */
interface EscapeRoomAccess
{
    /**
     * The current viewer as the host of the game, or null when they do not run it.
     */
    public function host(StoredGame $game): ?ResourceReference;

    /**
     * Whether the current viewer may watch the hint screen of the game: the room's screen, the team, the host.
     */
    public function watches(StoredGame $game): bool;

    /**
     * Whether the current viewer speaks for the team: who agrees to show its result on the public leaderboard.
     */
    public function speaksForTeam(StoredGame $game): bool;
}
