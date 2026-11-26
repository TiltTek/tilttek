<?php

declare(strict_types=1);

namespace Tilttek\TurnClock\Exception;

use RuntimeException;

/**
 * No clock has the identifier, or the clock has no such player.
 */
final class ClockNotFound extends RuntimeException implements TurnClockException
{
    public static function clock(string $id): self
    {
        return new self(\sprintf('No clock has the identifier %s.', $id));
    }

    public static function player(string $clock, string $id): self
    {
        return new self(\sprintf('The clock %s has no player %s.', $clock, $id));
    }
}
