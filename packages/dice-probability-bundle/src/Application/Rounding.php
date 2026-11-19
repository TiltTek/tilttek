<?php

declare(strict_types=1);

namespace Tilttek\DiceProbabilityBundle\Application;

use RoundingMode;

/**
 * How a division rounds when the expression does not say, by the name the configuration and the templates use: down,
 * up, to the nearest, towards zero or away from it.
 */
enum Rounding: string
{
    case Floor = 'floor';
    case Ceil = 'ceil';
    case Round = 'round';
    case TowardsZero = 'towards_zero';
    case AwayFromZero = 'away_from_zero';

    public function mode(): RoundingMode
    {
        return match ($this) {
            self::Floor => RoundingMode::NegativeInfinity,
            self::Ceil => RoundingMode::PositiveInfinity,
            self::Round => RoundingMode::HalfAwayFromZero,
            self::TowardsZero => RoundingMode::TowardsZero,
            self::AwayFromZero => RoundingMode::AwayFromZero,
        };
    }
}
