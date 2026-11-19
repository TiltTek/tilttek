<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Application\Contract;

/**
 * Tells the screens of a game that it moved, so that they read it again. It names the game and the move, nothing
 * else: the screens read the hint, the message and the countdown from the server.
 */
interface ScreenPublisher
{
    /**
     * @param string $move one of `GameDesk::MOVES`
     */
    public function moved(string $game, string $move): void;
}
