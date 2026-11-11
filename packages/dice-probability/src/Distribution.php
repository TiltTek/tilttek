<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability;

use Closure;
use InvalidArgumentException;
use Tilttek\Dice\Expression\Validator;
use Tilttek\DiceProbability\Exact\Fraction;
use Tilttek\DiceProbability\Exact\Natural;

/**
 * Every value an expression can give and the probability of each, as exact fractions over the number of equally likely
 * ways to roll — `6/36` for a 7 on `2d6` — or over the number of samples of an estimate.
 */
final readonly class Distribution
{
    /**
     * @var array<int, Natural> by value, in ascending order
     */
    public array $weights;

    /** The sum of the weights: the ways to roll, or the samples drawn. */
    public Natural $total;

    /** The probability that an explosion cut at its depth took part: the values above it are then too low. */
    public Fraction $remaining;

    /**
     * @param array<int, Natural> $weights by value, in any order; the zero weights are left out
     *
     * @throws InvalidArgumentException when no value has a weight
     */
    public function __construct(
        array $weights,
        ?Fraction $remaining = null,
        public ?Estimate $estimate = null,
    ) {
        $weights = array_filter($weights, static fn (Natural $weight): bool => !$weight->isZero());

        if ([] === $weights) {
            throw new InvalidArgumentException('A distribution has at least one value of some weight.');
        }

        ksort($weights);
        $total = Natural::zero();

        foreach ($weights as $weight) {
            $total = $total->add($weight);
        }

        $this->weights = $weights;
        $this->total = $total;
        $this->remaining = $remaining ?? Fraction::zero();
    }

    public function isEstimated(): bool
    {
        return $this->estimate instanceof Estimate;
    }

    /**
     * @return list<int> the values, in ascending order
     */
    public function values(): array
    {
        return array_keys($this->weights);
    }

    public function minimum(): int
    {
        return array_key_first($this->weights);
    }

    public function maximum(): int
    {
        return array_key_last($this->weights);
    }

    public function probability(int $value): Fraction
    {
        return new Fraction($this->weights[$value] ?? Natural::zero(), $this->total);
    }

    public function atLeast(int $value): Fraction
    {
        return $this->where(static fn (int $outcome): bool => $outcome >= $value);
    }

    public function atMost(int $value): Fraction
    {
        return $this->where(static fn (int $outcome): bool => $outcome <= $value);
    }

    /**
     * The probability that the value matches a validator of the notation: `new Comparison(Comparator::Greater, 10)`,
     * `new Range(8, 10)`.
     */
    public function matching(Validator $validator): Fraction
    {
        return $this->where($validator->matches(...));
    }

    /**
     * Each value with its probability, at least and at most, in ascending order: the rows of a table.
     *
     * @return list<Outcome>
     */
    public function outcomes(): array
    {
        $outcomes = [];
        $below = Natural::zero();

        foreach ($this->weights as $value => $weight) {
            $outcomes[] = new Outcome(
                $value,
                new Fraction($weight, $this->total),
                new Fraction($this->total->subtract($below), $this->total),
                new Fraction($below->add($weight), $this->total),
            );
            $below = $below->add($weight);
        }

        return $outcomes;
    }

    /**
     * The mean, exact.
     */
    public function mean(): Fraction
    {
        return $this->moment(1);
    }

    /**
     * The variance, exact: the mean of the squares less the square of the mean.
     */
    public function variance(): Fraction
    {
        return $this->moment(2)->subtract($this->mean()->power(2));
    }

    public function standardDeviation(): float
    {
        return sqrt($this->variance()->toFloat());
    }

    /**
     * The probability of the outcomes that match.
     *
     * @param Closure(int): bool $matches
     */
    private function where(Closure $matches): Fraction
    {
        $weight = Natural::zero();

        foreach ($this->weights as $value => $outcome) {
            if ($matches($value)) {
                $weight = $weight->add($outcome);
            }
        }

        return new Fraction($weight, $this->total);
    }

    /**
     * The mean of the values raised to a power, over the total.
     */
    private function moment(int $power): Fraction
    {
        $sum = Fraction::zero();

        foreach ($this->weights as $value => $weight) {
            $term = Fraction::of($value)->power($power);
            $sum = $sum->add(new Fraction($term->numerator->multiply($weight), Natural::one(), $term->negative));
        }

        return new Fraction($sum->numerator, $this->total, $sum->negative);
    }
}
