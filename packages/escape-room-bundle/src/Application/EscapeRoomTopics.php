<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Application;

/**
 * The topic of a game's screens on a Mercure hub: the prefix of the configuration, `/games/` and the game's identifier.
 */
final readonly class EscapeRoomTopics
{
    public function __construct(
        private string $prefix = 'tilttek-escape-room',
    ) {
    }

    public function topic(string $game): string
    {
        return $this->prefix.'/games/'.rawurlencode($game);
    }
}
