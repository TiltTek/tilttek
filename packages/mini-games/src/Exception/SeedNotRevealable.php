<?php

declare(strict_types=1);

namespace Tilttek\MiniGames\Exception;

use RuntimeException;

/**
 * The finished log of a daily game asked for while its day is not over: its seed is the one every player of the day
 * shares, and it would give away the game to those who have not played it.
 */
final class SeedNotRevealable extends RuntimeException implements MiniGameException
{
    public static function until(string $game, string $day): self
    {
        return new self(\sprintf('The seed of the daily game "%s" of %s is shown once the day is over.', $game, $day));
    }
}
