<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: a name, a board, a period, a season, a pseudonym, a score or a circle that does not
 * hold together, or a request a board cannot answer.
 */
final class InvalidLeaderboard extends InvalidArgumentException implements LeaderboardsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
