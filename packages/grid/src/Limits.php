<?php

declare(strict_types=1);

namespace Tilttek\Grid;

use Tilttek\Grid\Exception\OutOfBounds;

/**
 * The bounds that keep every computation of the library short and exact, whatever its input.
 */
final class Limits
{
    /**
     * The farthest a cell coordinate may be from the origin, on either axis.
     */
    public const int COORDINATE = 1_000_000;

    /**
     * The most steps of a line between two cells.
     */
    public const int LINE = 10_000;

    /**
     * The largest radius of an area or a ring.
     */
    public const int RADIUS = 100;

    /**
     * The largest radius of a field of view.
     */
    public const int SIGHT = 30;

    /**
     * @throws OutOfBounds when the value is farther than {@see self::COORDINATE} from zero
     */
    public static function coordinate(int $value, string $name): int
    {
        if ($value < -self::COORDINATE || $value > self::COORDINATE) {
            throw OutOfBounds::because(\sprintf('A coordinate lies from -%2$d to %2$d; %1$s is %3$d.', $name, self::COORDINATE, $value));
        }

        return $value;
    }

    /**
     * @throws OutOfBounds when the radius is negative or larger than the maximum
     */
    public static function radius(int $radius, int $maximum, string $what): int
    {
        if ($radius < 0 || $radius > $maximum) {
            throw OutOfBounds::because(\sprintf('The radius of %s lies from 0 to %d; it is %d.', $what, $maximum, $radius));
        }

        return $radius;
    }

    /**
     * @throws OutOfBounds when the line has more steps than {@see self::LINE}
     */
    public static function line(int $steps): int
    {
        if ($steps > self::LINE) {
            throw OutOfBounds::because(\sprintf('A line has at most %d steps; this one has %d.', self::LINE, $steps));
        }

        return $steps;
    }

    /**
     * The floor of a division by a positive divisor, which `intdiv()` rounds towards zero instead.
     */
    public static function floorDiv(int $dividend, int $divisor): int
    {
        $quotient = intdiv($dividend, $divisor);

        return $dividend % $divisor < 0 ? $quotient - 1 : $quotient;
    }

    /**
     * The nearest integer to a fraction of a positive denominator, a half rounded up.
     */
    public static function roundHalfUp(int $numerator, int $denominator): int
    {
        return self::floorDiv(2 * $numerator + $denominator, 2 * $denominator);
    }
}
