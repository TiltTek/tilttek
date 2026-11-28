<?php

declare(strict_types=1);

namespace Tilttek\Grid;

use RoundingMode;
use Stringable;
use Tilttek\Grid\Exception\InvalidGrid;
use Tilttek\Grid\Exception\OutOfBounds;

/**
 * An exact fraction of two integers, kept in lowest terms with a positive denominator: the sizes and the positions of
 * a grid in pixels, which a scanned map rarely gives as whole numbers. No floating-point number is involved, so a
 * position always falls in the same cell.
 */
final readonly class Ratio implements Stringable
{
    public int $numerator;

    public int $denominator;

    /**
     * @throws InvalidGrid when the denominator is zero
     * @throws OutOfBounds when a term is the smallest integer, which has no opposite
     */
    private function __construct(int $numerator, int $denominator)
    {
        if (0 === $denominator) {
            throw InvalidGrid::because('A ratio has a denominator other than zero.');
        }

        if (\PHP_INT_MIN === $numerator || \PHP_INT_MIN === $denominator) {
            throw OutOfBounds::because('A term of a ratio is larger than exact arithmetic allows.');
        }

        if ($denominator < 0) {
            $numerator = -$numerator;
            $denominator = -$denominator;
        }

        $divisor = $this->gcd(abs($numerator), $denominator);
        $this->numerator = intdiv($numerator, $divisor);
        $this->denominator = intdiv($denominator, $divisor);
    }

    /**
     * @throws InvalidGrid when the denominator is zero
     * @throws OutOfBounds when a term is the smallest integer
     */
    public static function of(int $numerator, int $denominator = 1): self
    {
        return new self($numerator, $denominator);
    }

    /**
     * @throws OutOfBounds when the result is larger than exact arithmetic allows
     */
    public function plus(self $other): self
    {
        return new self(
            $this->add($this->multiply($this->numerator, $other->denominator), $this->multiply($other->numerator, $this->denominator)),
            $this->multiply($this->denominator, $other->denominator),
        );
    }

    /**
     * @throws OutOfBounds when the result is larger than exact arithmetic allows
     */
    public function minus(self $other): self
    {
        return $this->plus($other->negated());
    }

    /**
     * @throws OutOfBounds when the result is larger than exact arithmetic allows
     */
    public function times(self $other): self
    {
        return new self($this->multiply($this->numerator, $other->numerator), $this->multiply($this->denominator, $other->denominator));
    }

    /**
     * @throws InvalidGrid when the divisor is zero
     * @throws OutOfBounds when the result is larger than exact arithmetic allows
     */
    public function dividedBy(self $other): self
    {
        return new self($this->multiply($this->numerator, $other->denominator), $this->multiply($this->denominator, $other->numerator));
    }

    public function negated(): self
    {
        return new self(-$this->numerator, $this->denominator);
    }

    public function abs(): self
    {
        return new self(abs($this->numerator), $this->denominator);
    }

    /**
     * -1, 0 or 1 as this ratio is smaller than, equal to or larger than the other.
     *
     * @throws OutOfBounds when the cross products are larger than exact arithmetic allows
     */
    public function compare(self $other): int
    {
        return $this->multiply($this->numerator, $other->denominator) <=> $this->multiply($other->numerator, $this->denominator);
    }

    public function equals(self $other): bool
    {
        return $this->numerator === $other->numerator && $this->denominator === $other->denominator;
    }

    public function isPositive(): bool
    {
        return $this->numerator > 0;
    }

    /**
     * The largest integer not above this ratio.
     */
    public function floor(): int
    {
        return Limits::floorDiv($this->numerator, $this->denominator);
    }

    /**
     * The integer this ratio rounds to by the mode given.
     */
    public function round(RoundingMode $mode = RoundingMode::HalfAwayFromZero): int
    {
        $floor = $this->floor();
        $remainder = $this->numerator - $floor * $this->denominator;

        return $this->resolve($floor, 0 === $remainder, (2 * $remainder) <=> $this->denominator, $this->numerator > 0, $mode);
    }

    /**
     * The integer the square root of this ratio rounds to by the mode given, computed without a floating-point number.
     *
     * @throws InvalidGrid when the ratio is negative
     * @throws OutOfBounds when the comparison is larger than exact arithmetic allows
     */
    public function sqrtRounded(RoundingMode $mode = RoundingMode::HalfAwayFromZero): int
    {
        if ($this->numerator < 0) {
            throw InvalidGrid::because('A negative ratio has no square root.');
        }

        // A floating-point root is correctly rounded, so it never falls below the integer root; it can reach the next
        // integer from below only when the quotient has more bits than a float keeps.
        $floor = (int) sqrt(intdiv($this->numerator, $this->denominator));
        while ($this->multiply($this->multiply($floor, $floor), $this->denominator) > $this->numerator) {
            --$floor;
        }

        $exact = $this->multiply($this->multiply($floor, $floor), $this->denominator) === $this->numerator;
        $half = $this->multiply(4, $this->numerator) <=> $this->multiply($this->multiply(2 * $floor + 1, 2 * $floor + 1), $this->denominator);

        return $this->resolve($floor, $exact, $half, $this->numerator > 0, $mode);
    }

    /**
     * The ratio written as a decimal number of the scale given, rounded by the mode given: for display only.
     *
     * @param int<0, 12> $scale
     */
    public function toDecimal(int $scale = 2, RoundingMode $mode = RoundingMode::HalfAwayFromZero): string
    {
        $units = $this->times(self::of(10 ** $scale))->round($mode);
        $sign = $units < 0 ? '-' : '';
        $digits = str_pad((string) abs($units), $scale + 1, '0', \STR_PAD_LEFT);

        return 0 === $scale ? $sign.$digits : $sign.substr($digits, 0, -$scale).'.'.substr($digits, -$scale);
    }

    public function __toString(): string
    {
        return 1 === $this->denominator ? (string) $this->numerator : $this->numerator.'/'.$this->denominator;
    }

    /**
     * @param bool $exact    whether the value is exactly the floor
     * @param int  $half     -1, 0 or 1 as the value is below, at or above the floor plus one half
     * @param bool $positive whether the value is above zero
     */
    private function resolve(int $floor, bool $exact, int $half, bool $positive, RoundingMode $mode): int
    {
        if ($exact) {
            return $floor;
        }

        $up = match ($mode) {
            RoundingMode::PositiveInfinity => true,
            RoundingMode::NegativeInfinity => false,
            RoundingMode::AwayFromZero => $positive,
            RoundingMode::TowardsZero => !$positive,
            default => 0 !== $half ? $half > 0 : match ($mode) {
                RoundingMode::HalfAwayFromZero => $positive,
                RoundingMode::HalfTowardsZero => !$positive,
                RoundingMode::HalfEven => 0 !== $floor % 2,
                RoundingMode::HalfOdd => 0 === $floor % 2,
            },
        };

        return $up ? $floor + 1 : $floor;
    }

    private function gcd(int $a, int $b): int
    {
        while (0 !== $b) {
            [$a, $b] = [$b, $a % $b];
        }

        return $a;
    }

    /**
     * @throws OutOfBounds when the product leaves the integers
     */
    private function multiply(int $a, int $b): int
    {
        if (0 !== $a && abs($b) > intdiv(\PHP_INT_MAX, abs($a))) {
            throw OutOfBounds::because('A product is larger than exact arithmetic allows.');
        }

        return $a * $b;
    }

    /**
     * @throws OutOfBounds when the sum leaves the integers
     */
    private function add(int $a, int $b): int
    {
        if (($b > 0 && $a > \PHP_INT_MAX - $b) || ($b < 0 && $a < -\PHP_INT_MAX - $b)) {
            throw OutOfBounds::because('A sum is larger than exact arithmetic allows.');
        }

        return $a + $b;
    }
}
