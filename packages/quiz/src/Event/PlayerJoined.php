<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Event;

/**
 * Dispatched when a player joins a live session for the first time: the host's screen counts the players. It carries
 * identifiers only, never a pseudonym.
 */
final readonly class PlayerJoined
{
    public function __construct(
        public string $session,
        public string $player,
    ) {
    }
}
