<?php

declare(strict_types=1);

namespace Tilttek\Dice\Rolling;

use RoundingMode;
use Tilttek\Dice\Exception\CannotRoll;

/**
 * Integer arithmetic, without floating point: a division rounds as the mode given says, a remainder has the sign of
 * the divisor, a power with a negative exponent is one divided by the power, and a result beyond the range of an
 * integer is refused.
 */
final class Arithmetic
{
    /**
     * @throws CannotRoll on a division by zero or an overflow
     */
    public static function divide(int $left, int $right, RoundingMode $rounding): int
    {
        if (0 === $right) {
            throw CannotRoll::divisionByZero();
        }

        if (-1 === $right) {
            return self::integer(-$left);
        }

        $quotient = intdiv($left, $right);
        $remainder = abs($left % $right);

        if (0 === $remainder) {
            return $quotient;
        }

        $negative = ($left < 0) !== ($right < 0);
        $away = $negative ? $quotient - 1 : $quotient + 1;
        // What is left to reach the divisor, compared with the remainder: whether the quotient is past its half.
        $half = $remainder <=> -(min($right, -$right) + $remainder);

        return match ($rounding) {
            RoundingMode::TowardsZero => $quotient,
            RoundingMode::AwayFromZero => $away,
            RoundingMode::NegativeInfinity => $negative ? $away : $quotient,
            RoundingMode::PositiveInfinity => $negative ? $quotient : $away,
            default => match ($half) {
                -1 => $quotient,
                1 => $away,
                default => match ($rounding) {
                    RoundingMode::HalfAwayFromZero => $away,
                    RoundingMode::HalfTowardsZero => $quotient,
                    RoundingMode::HalfEven => 0 === $quotient % 2 ? $quotient : $away,
                    default => 0 === $quotient % 2 ? $away : $quotient,
                },
            },
        };
    }

    /**
     * @throws CannotRoll on a division by zero
     */
    public static function modulo(int $left, int $right): int
    {
        if (0 === $right) {
            throw CannotRoll::divisionByZero();
        }

        $remainder = $left % $right;

        return 0 !== $remainder && ($remainder < 0) !== ($right < 0) ? $remainder + $right : $remainder;
    }

    /**
     * @throws CannotRoll on zero to a negative power, a division by zero, or an overflow
     */
    public static function power(int $base, int $exponent, RoundingMode $rounding): int
    {
        if ($exponent >= 0) {
            return self::raise($base, $exponent) ?? throw CannotRoll::overflow();
        }

        // Beyond the range of an integer, the power is replaced by the largest integer of its sign: one divided by
        // either rounds the same way.
        $power = self::raise($base, -$exponent) ?? ($base < 0 && 0 !== $exponent % 2 ? -\PHP_INT_MAX : \PHP_INT_MAX);

        return self::divide(1, $power, $rounding);
    }

    public static function add(int $left, int $right): int
    {
        return self::integer($left + $right);
    }

    public static function subtract(int $left, int $right): int
    {
        return self::integer($left - $right);
    }

    public static function multiply(int $left, int $right): int
    {
        return self::integer($left * $right);
    }

    /**
     * @throws CannotRoll on an overflow
     */
    public static function integer(int|float $value): int
    {
        return \is_int($value) ? $value : throw CannotRoll::overflow();
    }

    /**
     * The power by squaring, or null beyond the range of an integer.
     */
    private static function raise(int $base, int $exponent): ?int
    {
        $result = 1;

        while ($exponent > 0) {
            if (1 === $exponent % 2) {
                $result = self::fits($result * $base);
            }

            $exponent = intdiv($exponent, 2);

            if ($exponent > 0) {
                $base = self::fits($base * $base);
            }

            if (null === $result || null === $base) {
                return null;
            }
        }

        return $result;
    }

    private static function fits(int|float $value): ?int
    {
        return \is_int($value) ? $value : null;
    }
}
