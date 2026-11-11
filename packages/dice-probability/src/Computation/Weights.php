<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Computation;

use Closure;
use Tilttek\DiceProbability\Exact\Fraction;
use Tilttek\DiceProbability\Exact\Natural;

/**
 * The outcomes of a computation and their weights — how many equally likely ways lead to each —, over a total that is
 * the sum of the weights, with the probability that an explosion cut at its depth took part.
 *
 * @internal
 */
final readonly class Weights
{
    /**
     * @param array<int, Natural> $weights by outcome, in ascending order, none of them zero
     */
    private function __construct(
        public array $weights,
        public Natural $total,
        public Fraction $remaining,
    ) {
    }

    /**
     * @param array<int, Natural> $weights by outcome, in any order
     */
    public static function of(array $weights, ?Fraction $remaining = null): self
    {
        ksort($weights);
        $total = Natural::zero();

        foreach ($weights as $weight) {
            $total = $total->add($weight);
        }

        return new self($weights, $total, $remaining ?? Fraction::zero());
    }

    public static function constant(int $value): self
    {
        return self::of([$value => Natural::one()]);
    }

    /**
     * Each value once, equally likely: the faces of a die. A value given twice weighs twice.
     *
     * @param list<int> $values
     */
    public static function faces(array $values): self
    {
        $counts = array_count_values($values);

        return self::of(array_map(Natural::of(...), $counts));
    }

    /**
     * The outcomes of two independent computations joined by a function.
     *
     * @param Closure(int, int): int $join
     */
    public function combine(self $other, Closure $join, Budget $budget): self
    {
        $budget->spend(\count($this->weights) * \count($other->weights));
        $weights = [];

        foreach ($this->weights as $left => $leftWeight) {
            foreach ($other->weights as $right => $rightWeight) {
                $value = $join($left, $right);
                $weight = $leftWeight->multiply($rightWeight);
                $weights[$value] = isset($weights[$value]) ? $weights[$value]->add($weight) : $weight;
            }
        }

        return self::of($weights, self::either($this->remaining, $other->remaining));
    }

    /**
     * Each outcome turned into another.
     *
     * @param Closure(int): int $map
     */
    public function map(Closure $map, Budget $budget): self
    {
        $budget->spend(\count($this->weights));
        $weights = [];

        foreach ($this->weights as $value => $weight) {
            $mapped = $map($value);
            $weights[$mapped] = isset($weights[$mapped]) ? $weights[$mapped]->add($weight) : $weight;
        }

        return self::of($weights, $this->remaining);
    }

    /**
     * The outcomes of a computation that depends on each outcome of this one: the sides of `3d(1d4*2)`, the dice of
     * `(1d4)d6`. The inner computations are brought to a common total, the least common multiple of theirs.
     *
     * @param Closure(int): self $inner
     */
    public function mixture(Closure $inner, Budget $budget): self
    {
        $inners = [];
        $common = Natural::one();

        foreach (array_keys($this->weights) as $value) {
            $inners[$value] = $inner($value);
            $total = $inners[$value]->total;
            $common = $common->multiply($total)->divide($common->gcd($total))[0];
        }

        $weights = [];
        $remaining = Fraction::zero();

        foreach ($inners as $value => $distribution) {
            $budget->spend(\count($distribution->weights));
            $scale = $this->weights[$value]->multiply($common->divide($distribution->total)[0]);

            foreach ($distribution->weights as $outcome => $weight) {
                $weight = $weight->multiply($scale);
                $weights[$outcome] = isset($weights[$outcome]) ? $weights[$outcome]->add($weight) : $weight;
            }

            $remaining = $remaining->add(new Fraction($this->weights[$value], $this->total)->multiply($distribution->remaining));
        }

        return self::of($weights, self::either($this->remaining, $remaining->reduced()));
    }

    /**
     * The sum of a number of independent copies, by squaring: `10d6` from `1d6`.
     */
    public function sumOf(int $count, Budget $budget): self
    {
        $plain = $this->plain();

        if (null !== $plain) {
            return $this->slide($count, $plain[0], $plain[1], $plain[2], $budget);
        }

        $add = static fn (int $left, int $right): int => $left + $right;
        $result = self::constant(0);
        $power = $this;

        while ($count > 0) {
            if (1 === $count % 2) {
                $result = $result->combine($power, $add, $budget);
            }

            $count = intdiv($count, 2);

            if ($count > 0) {
                $power = $power->combine($power, $add, $budget);
            }
        }

        return $result;
    }

    /**
     * The lowest and the highest outcome and the weight of each, when the outcomes are consecutive integers of the same
     * weight — the faces of a plain die —; null otherwise.
     *
     * @return ?array{int, int, Natural}
     */
    private function plain(): ?array
    {
        $plain = null;

        foreach ($this->weights as $value => $weight) {
            if (null !== $plain && ($plain[1] + 1 !== $value || !$weight->equals($plain[2]))) {
                return null;
            }

            $plain = [$plain[0] ?? $value, $value, $weight];
        }

        return $plain;
    }

    /**
     * The sum of copies of a plain die, one die at a time: each sum of one more die is the window of the sums before
     * it that a face reaches, slid along — a few additions per sum rather than one per face.
     */
    private function slide(int $count, int $lowest, int $highest, Natural $face, Budget $budget): self
    {
        $sums = [0 => Natural::one()];

        for ($dice = 0; $dice < $count; ++$dice) {
            $budget->spend(\count($sums) + $highest - $lowest);
            $window = Natural::zero();
            $next = [];

            for ($sum = ($dice + 1) * $lowest; $sum <= ($dice + 1) * $highest; ++$sum) {
                $window = $window->add($sums[$sum - $lowest] ?? Natural::zero());

                if (isset($sums[$sum - $highest - 1])) {
                    $window = $window->subtract($sums[$sum - $highest - 1]);
                }

                $next[$sum] = $window->multiply($face);
            }

            $sums = $next;
        }

        return self::of($sums, Fraction::one()->subtract($this->remaining->complement()->power($count))->reduced());
    }

    /**
     * The total weight of the outcomes that match.
     *
     * @param Closure(int): bool $matches
     */
    public function weightOf(Closure $matches): Natural
    {
        $weight = Natural::zero();

        foreach ($this->weights as $value => $outcome) {
            if ($matches($value)) {
                $weight = $weight->add($outcome);
            }
        }

        return $weight;
    }

    /**
     * The probability that either of two independent events happens.
     */
    public static function either(Fraction $first, Fraction $second): Fraction
    {
        return $first->add($second)->subtract($first->multiply($second))->reduced();
    }
}
