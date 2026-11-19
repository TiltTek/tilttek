<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Application;

use Tilttek\EscapeRoom\Game;
use Tilttek\EscapeRoom\Room;

/**
 * A game as the bundle keeps it: its identifier, its room and its state.
 */
final readonly class StoredGame
{
    public function __construct(
        public string $id,
        public Room $room,
        public Game $game,
    ) {
    }
}
