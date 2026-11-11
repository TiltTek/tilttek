<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Exact;

use InvalidArgumentException;
use Override;
use Stringable;

/**
 * An exact rational number: a probability, a mean or a variance. It keeps the denominator it was computed over —
 * `16/36` for two six-sided dice, the count of their outcomes — and `reduced()` gives its lowest terms, `4/9`.
 */
final readonly class Fraction implements Stringable
{
    public Natural $denominator;

    /** Whether the fraction is below zero; zero is never negative. */
    public bool $negative;

    /**
     * @throws InvalidArgumentException when the denominator is zero
     */
    public function __construct(public Natural $numerator, Natural $denominator, bool $negative = false)
    {
        if ($denominator->isZero()) {
            throw new InvalidArgumentException('A fraction has a denominator other than zero.');
        }

        $this->denominator = $denominator;
        $this->negative = $negative && !$this->numerator->isZero();
    }

    /**
     * @throws InvalidArgumentException when the denominator is zero
     */
    public static function of(int $numerator, int $denominator = 1): self
    {
        // The exclusive or of two integers is negative exactly when their signs differ.
        return new self(Natural::parse(ltrim((string) $numerator, '-')), Natural::parse(ltrim((string) $denominator, '-')), ($numerator ^ $denominator) < 0);
    }

    public static function zero(): self
    {
        return new self(Natural::zero(), Natural::one());
    }

    public static function one(): self
    {
        return new self(Natural::one(), Natural::one());
    }

    public function isZero(): bool
    {
        return $this->numerator->isZero();
    }

    public function add(self $other): self
    {
        $left = $this->numerator->multiply($other->denominator);
        $right = $other->numerator->multiply($this->denominator);
        $denominator = $this->denominator->multiply($other->denominator);

        if ($this->negative === $other->negative) {
            return new self($left->add($right), $denominator, $this->negative);
        }

        // Opposite signs: the larger magnitude gives its sign to the difference.
        [$difference, $smaller] = $left->difference($right);

        return new self($difference, $denominator, $this->negative !== $smaller);
    }

    public function subtract(self $other): self
    {
        return $this->add($other->negate());
    }

    public function multiply(self $other): self
    {
        return new self($this->numerator->multiply($other->numerator), $this->denominator->multiply($other->denominator), $this->negative !== $other->negative);
    }

    /**
     * The fraction raised to a power of zero or more.
     *
     * @throws InvalidArgumentException when the exponent is negative
     */
    public function power(int $exponent): self
    {
        return new self($this->numerator->power($exponent), $this->denominator->power($exponent), $this->negative && 1 === $exponent % 2);
    }

    public function negate(): self
    {
        return new self($this->numerator, $this->denominator, !$this->negative);
    }

    /**
     * One minus the fraction: the probability that an event does not happen.
     */
    public function complement(): self
    {
        return self::one()->subtract($this)->over($this->denominator);
    }

    /**
     * -1, 0 or 1 as the fraction is smaller than, equal to or larger than the other.
     */
    public function compare(self $other): int
    {
        $difference = $this->subtract($other);

        return $difference->isZero() ? 0 : ($difference->negative ? -1 : 1);
    }

    /**
     * Whether both fractions are the same number, whatever their denominators: `16/36` equals `4/9`.
     */
    public function equals(self $other): bool
    {
        return 0 === $this->compare($other);
    }

    /**
     * The fraction in its lowest terms.
     */
    public function reduced(): self
    {
        $divisor = $this->numerator->gcd($this->denominator);

        return new self($this->numerator->divide($divisor)[0], $this->denominator->divide($divisor)[0], $this->negative);
    }

    /**
     * The fraction written over another denominator when that denominator is a multiple of its own in lowest terms;
     * the fraction unchanged otherwise.
     */
    public function over(Natural $denominator): self
    {
        $reduced = $this->reduced();
        [$factor, $remainder] = $denominator->divide($reduced->denominator);

        return $remainder->isZero() ? new self($reduced->numerator->multiply($factor), $denominator, $this->negative) : $this;
    }

    public function toFloat(): float
    {
        $ratio = Natural::ratio($this->numerator, $this->denominator);

        return $this->negative ? -$ratio : $ratio;
    }

    /**
     * `16/36`, `-7/2`, `3/1`: always written with its denominator.
     */
    #[Override]
    public function __toString(): string
    {
        return ($this->negative ? '-' : '').$this->numerator.'/'.$this->denominator;
    }
}
