<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards;

/**
 * How a tie-break gathers the values of a member's gestures in a period: their number (`count`), their sum (`total`),
 * the highest (`highest`) or the lowest (`lowest`) of them; or the value the application reports with the score
 * (`reported`), for a board whose measure it keeps.
 */
enum Aggregate: string
{
    case Count = 'count';
    case Total = 'total';
    case Highest = 'highest';
    case Lowest = 'lowest';
    case Reported = 'reported';

    /**
     * The value once the amount of one more gesture is gathered with the one held; the amount alone for the first.
     */
    public function gather(?int $held, int $amount): int
    {
        if (null === $held) {
            return $amount;
        }

        return match ($this) {
            self::Count, self::Total => min(Score::MAX, $held + $amount),
            self::Highest => max($held, $amount),
            self::Lowest => min($held, $amount),
            self::Reported => $amount,
        };
    }
}
