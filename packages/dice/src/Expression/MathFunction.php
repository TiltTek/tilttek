<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

use RoundingMode;

/**
 * The functions of the notation. `floor`, `ceil` and `round` say how the divisions inside them round, whatever the
 * roll says; `abs` is the absolute value; `min` and `max` the smallest and largest of their arguments; `repeat` rolls
 * its first argument as many times as its second says, into a list. Every function but the three that round keeps
 * the rounding of the roll.
 */
enum MathFunction: string
{
    case Floor = 'floor';
    case Ceil = 'ceil';
    case Round = 'round';
    case Abs = 'abs';
    case Min = 'min';
    case Max = 'max';
    case Repeat = 'repeat';

    /**
     * How the divisions inside the function round, for `floor`, `ceil` and `round`.
     */
    public function rounding(): ?RoundingMode
    {
        return match ($this) {
            self::Floor => RoundingMode::NegativeInfinity,
            self::Ceil => RoundingMode::PositiveInfinity,
            self::Round => RoundingMode::HalfAwayFromZero,
            default => null,
        };
    }

    /**
     * Whether the function takes any number of arguments, two at least, rather than a fixed number.
     */
    public function isVariadic(): bool
    {
        return self::Min === $this || self::Max === $this;
    }

    /**
     * The number of arguments the function takes, or the least of them for `min` and `max`: two for `repeat` — what
     * it rolls and how many times —, one for the others.
     */
    public function arity(): int
    {
        return $this->isVariadic() || self::Repeat === $this ? 2 : 1;
    }
}
