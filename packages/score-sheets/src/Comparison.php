<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets;

/**
 * The condition of a bonus, against its threshold.
 */
enum Comparison: string
{
    case AtLeast = 'at_least';
    case AtMost = 'at_most';
    case Equals = 'equals';

    public function holds(int $value, int $threshold): bool
    {
        return match ($this) {
            self::AtLeast => $value >= $threshold,
            self::AtMost => $value <= $threshold,
            self::Equals => $value === $threshold,
        };
    }
}
