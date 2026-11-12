<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Exact;

use InvalidArgumentException;
use Override;
use Stringable;

/**
 * A natural number of any size — zero or more —, the count of outcomes behind an exact probability: `6^30` ways to
 * roll thirty six-sided dice is beyond a PHP integer, not beyond a `Natural`.
 *
 * It is kept in limbs of nine decimal digits, the lowest first, without leading zero limbs; zero has no limb.
 */
final readonly class Natural implements Stringable
{
    /** The base of a limb: nine decimal digits, whose product fits a 64-bit integer. */
    public const int BASE = 1_000_000_000;

    private const int DIGITS = 9;

    /** The leading digits a ratio is read from: 27 hold more than a float's precision. */
    private const int LEADING = 27;

    /** The highest power of two below the base: the first bit of a digit of a quotient. */
    private const int TOP_BIT = 536_870_912;

    /**
     * @param array<int, int> $limbs
     */
    private function __construct(
        private array $limbs,
    ) {
    }

    /**
     * @throws InvalidArgumentException when the integer is negative
     */
    public static function of(int $value): self
    {
        if ($value < 0) {
            throw new InvalidArgumentException(\sprintf('A natural number is zero or more; %d is not.', $value));
        }

        $limbs = [];

        while ($value > 0) {
            $limbs[] = $value % self::BASE;
            $value = intdiv($value, self::BASE);
        }

        return new self($limbs);
    }

    /**
     * @throws InvalidArgumentException when the text is not a run of decimal digits
     */
    public static function parse(string $digits): self
    {
        if (1 !== preg_match('/^\d+$/D', $digits)) {
            throw new InvalidArgumentException(\sprintf('A natural number is written with decimal digits only; "%s" is not.', $digits));
        }

        // Read from the right, nine digits at a time: the lowest limb first.
        return new self(self::trim(array_map(static fn (string $limb): int => (int) strrev($limb), str_split(strrev($digits), self::DIGITS))));
    }

    public static function zero(): self
    {
        return new self([]);
    }

    public static function one(): self
    {
        return new self([1]);
    }

    public function isZero(): bool
    {
        return [] === $this->limbs;
    }

    /**
     * The number of limbs, at least one: what an addition of this number costs, and a multiplication the product of
     * the sizes of its two numbers.
     *
     * @internal
     */
    public function size(): int
    {
        return max(1, \count($this->limbs));
    }

    public function add(self $other): self
    {
        $limbs = [];
        $carry = 0;
        $length = max(\count($this->limbs), \count($other->limbs));

        for ($index = 0; $index < $length; ++$index) {
            $sum = ($this->limbs[$index] ?? 0) + ($other->limbs[$index] ?? 0) + $carry;
            $carry = $sum >= self::BASE ? 1 : 0;
            $limbs[] = $sum - $carry * self::BASE;
        }

        if (1 === $carry) {
            $limbs[] = 1;
        }

        return new self($limbs);
    }

    /**
     * @throws InvalidArgumentException when the other number is larger: a natural number stays zero or more
     */
    public function subtract(self $other): self
    {
        if ($this->compare($other) < 0) {
            throw new InvalidArgumentException(\sprintf('%s cannot be taken from %s: a natural number stays zero or more.', $other, $this));
        }

        $limbs = [];
        $borrow = 0;

        foreach ($this->limbs as $index => $limb) {
            $difference = $limb - ($other->limbs[$index] ?? 0) - $borrow;
            $borrow = $difference < 0 ? 1 : 0;
            $limbs[] = $difference + $borrow * self::BASE;
        }

        return new self(self::trim($limbs));
    }

    public function multiply(self $other): self
    {
        $limbs = array_fill(0, \count($this->limbs) + \count($other->limbs), 0);

        foreach ($this->limbs as $i => $left) {
            $carry = 0;

            foreach ($other->limbs as $j => $right) {
                $product = $limbs[$i + $j] + $left * $right + $carry;
                $carry = intdiv($product, self::BASE);
                $limbs[$i + $j] = $product % self::BASE;
            }

            $limbs[$i + \count($other->limbs)] = $carry;
        }

        return new self(self::trim($limbs));
    }

    /**
     * The number raised to a power of zero or more.
     *
     * @throws InvalidArgumentException when the exponent is negative
     */
    public function power(int $exponent): self
    {
        if ($exponent < 0) {
            throw new InvalidArgumentException(\sprintf('A natural number is raised to a power of zero or more; %d is not.', $exponent));
        }

        $result = self::one();
        $base = $this;

        while ($exponent > 0) {
            if (1 === $exponent % 2) {
                $result = $result->multiply($base);
            }

            $exponent = intdiv($exponent, 2);
            $base = $base->multiply($base);
        }

        return $result;
    }

    /**
     * The quotient and the remainder of the division by a number that is not zero.
     *
     * @return array{self, self}
     *
     * @throws InvalidArgumentException when the divisor is zero
     */
    public function divide(self $divisor): array
    {
        if ($divisor->isZero()) {
            throw new InvalidArgumentException('A natural number is not divided by zero.');
        }

        $size = \count($divisor->limbs);

        if (\count($this->limbs) < $size) {
            return [self::zero(), $this];
        }

        $digits = [];
        // The highest limbs, one fewer than the divisor's, stay below it: the digits of the quotient start after them.
        $remainder = new self(self::trim(\array_slice($this->limbs, \count($this->limbs) - $size + 1)));

        for ($index = \count($this->limbs) - $size; $index >= 0; --$index) {
            $remainder = new self(self::trim([$this->limbs[$index], ...$remainder->limbs]));
            // The digit of the quotient, bit by bit from the highest: the largest whose product by the divisor stays
            // within the remainder, below the base since the remainder stays below the divisor times the base.
            $digit = 0;

            for ($bit = self::TOP_BIT; $bit > 0; $bit >>= 1) {
                if ($divisor->multiply(self::of($digit + $bit))->compare($remainder) <= 0) {
                    $digit += $bit;
                }
            }

            $digits[] = $digit;
            $remainder = $remainder->subtract($divisor->multiply(self::of($digit)));
        }

        return [new self(self::trim(array_reverse($digits))), $remainder];
    }

    /**
     * How far apart two numbers are, and whether this one is the smaller.
     *
     * @return array{self, bool}
     */
    public function difference(self $other): array
    {
        $smaller = $this->compare($other) < 0;

        return [$smaller ? $other->subtract($this) : $this->subtract($other), $smaller];
    }

    /**
     * The greatest common divisor; that of zero and a number is the number.
     */
    public function gcd(self $other): self
    {
        $a = $this;
        $b = $other;

        while (!$b->isZero()) {
            [, $remainder] = $a->divide($b);
            $a = $b;
            $b = $remainder;
        }

        return $a;
    }

    /**
     * -1, 0 or 1 as the number is smaller than, equal to or larger than the other.
     */
    public function compare(self $other): int
    {
        $length = \count($this->limbs) <=> \count($other->limbs);

        if (0 !== $length) {
            return $length;
        }

        for ($index = \count($this->limbs) - 1; $index >= 0; --$index) {
            if ($this->limbs[$index] !== $other->limbs[$index]) {
                return $this->limbs[$index] <=> $other->limbs[$index];
            }
        }

        return 0;
    }

    public function equals(self $other): bool
    {
        return $this->limbs === $other->limbs;
    }

    /**
     * The number as a PHP integer, when it fits one.
     */
    public function toInt(): ?int
    {
        $digits = (string) $this;

        return \strlen($digits) < 19 || (19 === \strlen($digits) && strcmp($digits, (string) \PHP_INT_MAX) <= 0) ? (int) $digits : null;
    }

    /**
     * The ratio of two numbers as a float, exact to the precision of a float however large both are; infinite when the
     * divisor is zero and the dividend is not.
     */
    public static function ratio(self $dividend, self $divisor): float
    {
        if ($dividend->isZero()) {
            return 0.0;
        }

        if ($divisor->isZero()) {
            return \INF;
        }

        // The leading digits of each hold more than a float's precision; the digits below them only shift the decimal
        // point.
        [$leading, $shift] = $dividend->leading();
        [$divisorLeading, $divisorShift] = $divisor->leading();

        return $leading / $divisorLeading * 10 ** ($shift - $divisorShift);
    }

    #[Override]
    public function __toString(): string
    {
        if ($this->isZero()) {
            return '0';
        }

        $limbs = array_reverse($this->limbs);
        $text = (string) array_shift($limbs);

        foreach ($limbs as $limb) {
            $text .= str_pad((string) $limb, self::DIGITS, '0', \STR_PAD_LEFT);
        }

        return $text;
    }

    /**
     * The leading digits as a float, and how many digits lie below them.
     *
     * @return array{float, int}
     */
    private function leading(): array
    {
        $digits = (string) $this;

        return [(float) substr($digits, 0, self::LEADING), max(0, \strlen($digits) - self::LEADING)];
    }

    /**
     * @param array<int, int> $limbs
     *
     * @return array<int, int>
     */
    private static function trim(array $limbs): array
    {
        while ([] !== $limbs && 0 === $limbs[\count($limbs) - 1]) {
            array_pop($limbs);
        }

        return $limbs;
    }
}
