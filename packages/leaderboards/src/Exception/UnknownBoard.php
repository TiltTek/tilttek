<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Exception;

use OutOfBoundsException;

/**
 * A board asked for by a name none has.
 */
final class UnknownBoard extends OutOfBoundsException implements LeaderboardsException
{
    public static function named(string $name): self
    {
        return new self(\sprintf('No board is named "%s".', $name));
    }
}
