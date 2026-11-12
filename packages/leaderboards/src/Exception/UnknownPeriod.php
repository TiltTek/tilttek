<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Exception;

use OutOfBoundsException;

/**
 * A period asked for by a code the board's schedule does not know — `2026-13`, a season never declared —, or the
 * current period of a board whose seasons leave the moment out.
 */
final class UnknownPeriod extends OutOfBoundsException implements LeaderboardsException
{
    public static function named(string $board, string $code): self
    {
        return new self(\sprintf('The board "%s" has no period coded "%s".', $board, $code));
    }

    public static function now(string $board): self
    {
        return new self(\sprintf('The board "%s" is between two seasons: no period is under way.', $board));
    }
}
