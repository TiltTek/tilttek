<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards;

/**
 * Which way a tie-break orders members with the same score: the highest value first (`desc`) — more games played —,
 * or the lowest first (`asc`) — fewer attempts, a shorter time.
 */
enum Direction: string
{
    case Descending = 'desc';
    case Ascending = 'asc';

    /**
     * The comparison of two values in this direction: negative when the first ranks before the second.
     */
    public function compare(int $first, int $second): int
    {
        return self::Descending === $this ? $second <=> $first : $first <=> $second;
    }
}
