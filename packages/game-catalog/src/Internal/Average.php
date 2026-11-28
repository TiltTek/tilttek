<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Internal;

/**
 * Averages in hundredths of whole numbers, so that results compare and display without floating point.
 *
 * @internal
 */
final class Average
{
    private function __construct()
    {
    }

    /**
     * The average of the votes in hundredths, rounded half up, or `null` without a vote.
     */
    public static function hundredths(int $sum, int $votes): ?int
    {
        return 0 === $votes ? null : intdiv(200 * $sum + $votes, 2 * $votes);
    }
}
