<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Exception;

use DomainException;

/**
 * A move the state of the game does not allow: the game is over, paused, out of time, or the move is not the host's
 * to make. Nothing changes when one is refused.
 */
final class GameRefused extends DomainException implements EscapeRoomException
{
    public static function over(string $room): self
    {
        return new self(\sprintf('The game of the room "%s" is over.', $room));
    }

    public static function paused(string $room): self
    {
        return new self(\sprintf('The game of the room "%s" is paused.', $room));
    }

    public static function running(string $room): self
    {
        return new self(\sprintf('The game of the room "%s" is not paused.', $room));
    }

    public static function timeUp(string $room): self
    {
        return new self(\sprintf('The time of the room "%s" is up: the game ends by expire().', $room));
    }

    public static function timeLeft(string $room): self
    {
        return new self(\sprintf('The time of the room "%s" is not up yet.', $room));
    }

    public static function otherRoom(string $room, string $game): self
    {
        return new self(\sprintf('The game is one of the room "%s", not "%s".', $game, $room));
    }

    public static function notEscaped(string $room): self
    {
        return new self(\sprintf('The team has not escaped the room "%s": it has no result to show.', $room));
    }
}
