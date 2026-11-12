<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Exception;

use LogicException;

/**
 * A snapshot asked for a period that has not ended — or never ends, for a board ranked since ever.
 */
final class PeriodNotOver extends LogicException implements LeaderboardsException
{
    public static function of(string $board, string $period): self
    {
        return new self(\sprintf('The period "%s" of the board "%s" is not over: it cannot be frozen yet.', $period, $board));
    }
}
