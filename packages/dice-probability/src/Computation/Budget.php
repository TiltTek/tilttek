<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Computation;

use Tilttek\DiceProbability\Exact\Fraction;
use Tilttek\DiceProbability\Exact\Natural;

/**
 * The work an exact computation may do, past which it gives way to an estimate: an operation on small weights counts
 * one; on larger ones, the product of their sizes in limbs, as long as the arithmetic takes; a fraction brought to its
 * lowest terms, or any greatest common divisor, the cube of its size.
 *
 * @internal
 */
final class Budget
{
    /** The products of limbs that take as long as the rest of an operation on weights. */
    private const int LIMBS = 16;

    private int $spent = 0;

    public function __construct(
        public readonly int $limit,
    ) {
    }

    /**
     * @throws TooLarge when the work goes past the limit
     */
    public function spend(int $work): void
    {
        if ($work > $this->limit - $this->spent) {
            throw new TooLarge();
        }

        $this->spent += $work;
    }

    /**
     * Operations on weights as large as these numbers: each costs the product of their sizes.
     *
     * @throws TooLarge when the work goes past the limit
     */
    public function spendOn(int $operations, Natural $left, ?Natural $right = null): void
    {
        $cost = 1 + intdiv($left->size() * ($right?->size() ?? 1), self::LIMBS);

        if ($operations > intdiv($this->limit, $cost)) {
            throw new TooLarge();
        }

        $this->spend($operations * $cost);
    }

    /**
     * The fraction in its lowest terms: the search for the common divisor of numbers of a size costs its cube.
     *
     * @throws TooLarge when the work goes past the limit
     */
    public function reduce(Fraction $fraction): Fraction
    {
        $this->spendOnDivisor($fraction->denominator);

        return $fraction->reduced();
    }

    /**
     * The greatest common divisor of a number and a larger one: its search costs the cube of the smaller's size.
     *
     * @throws TooLarge when the work goes past the limit
     */
    public function spendOnDivisor(Natural $number): void
    {
        $size = $number->size();
        $this->spend($size * $size * $size);
    }
}
