<?php

declare(strict_types=1);

namespace Tilttek\Conditions;

/**
 * How a multiplication rounds to an integer: the game system's rule, declared by the site.
 */
enum Rounding: string
{
    /** Toward negative infinity. */
    case Down = 'down';

    /** Toward positive infinity. */
    case Up = 'up';

    /** To the nearest, a half away from zero. */
    case HalfUp = 'half-up';

    /** Toward zero. */
    case TowardZero = 'toward-zero';

    /**
     * The quotient of two integers, the divisor above zero, rounded by the rule.
     */
    public function divide(int $dividend, int $divisor): int
    {
        $quotient = intdiv($dividend, $divisor);
        $remainder = $dividend % $divisor;

        return match ($this) {
            self::TowardZero => $quotient,
            self::Down => $remainder < 0 ? $quotient - 1 : $quotient,
            self::Up => $remainder > 0 ? $quotient + 1 : $quotient,
            self::HalfUp => $quotient + intdiv(2 * $remainder, $divisor),
        };
    }
}
