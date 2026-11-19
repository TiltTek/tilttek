<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Statistics;

use Tilttek\DiceJail\Exception\InvalidRolls;
use Tilttek\DiceProbability\Distribution;
use Tilttek\DiceProbability\Exact\Fraction;
use Tilttek\DiceProbability\Exact\Natural;

/**
 * The probability of each face of a fair die, as exact fractions: the yardstick its rolls are measured against.
 */
final readonly class ExpectedFaces
{
    /** The most faces a die judged by the statistics has. */
    public const int MAX_FACES = 1_000;

    /**
     * @param array<int, Fraction> $probabilities by face, in ascending order, each above zero
     */
    private function __construct(
        public array $probabilities,
    ) {
    }

    /**
     * A die whose faces from 1 to `$sides` are equally likely: a d6, a d20, a d100.
     *
     * @throws InvalidRolls when the die has fewer than two or more than `MAX_FACES` faces
     */
    public static function uniform(int $sides): self
    {
        return self::range(1, $sides);
    }

    /**
     * A die whose faces from `$lowest` to `$highest` are equally likely: a d10 numbered from 0, a fudge die from -1.
     *
     * @throws InvalidRolls when the range holds fewer than two or more than `MAX_FACES` faces
     */
    public static function range(int $lowest, int $highest): self
    {
        if ($highest < $lowest) {
            throw InvalidRolls::emptyRange($lowest, $highest);
        }

        $faces = $highest - $lowest + 1;
        self::check($faces);
        $probability = Fraction::of(1, $faces);

        return new self(array_fill_keys(range($lowest, $highest), $probability));
    }

    /**
     * The exact distribution `tilttek/dice-probability` computes for an expression — one die with weighted or custom
     * faces, or a total such as `2d6` judged as one outcome per roll.
     *
     * @throws InvalidRolls when the distribution is estimated, cuts its explosions, or has fewer than two or more than
     *                      `MAX_FACES` values
     */
    public static function fromDistribution(Distribution $distribution): self
    {
        if ($distribution->isEstimated()) {
            throw InvalidRolls::estimated();
        }

        if (!$distribution->remaining->isZero()) {
            throw InvalidRolls::truncated();
        }

        $values = $distribution->values();
        self::check(\count($values));
        $probabilities = [];

        foreach ($values as $value) {
            $probabilities[$value] = $distribution->probability($value)->reduced();
        }

        return new self($probabilities);
    }

    /** Whether the die can show the face. */
    public function has(int $face): bool
    {
        return isset($this->probabilities[$face]);
    }

    /** How many faces the die can show. */
    public function faces(): int
    {
        return \count($this->probabilities);
    }

    /** The mean of a fair roll. */
    public function mean(): float
    {
        $mean = 0.0;

        foreach ($this->probabilities as $face => $probability) {
            $mean += $face * $probability->toFloat();
        }

        return $mean;
    }

    /**
     * The fewest rolls for which every face is expected at least `$perFace` times, the condition under which the
     * chi-squared test is trusted.
     *
     * @throws InvalidRolls when that number passes `Tally::MAX_ROLLS`
     */
    public function minimumRolls(int $perFace): int
    {
        $rarest = null;

        foreach ($this->probabilities as $probability) {
            if (!$rarest instanceof Fraction || $probability->compare($rarest) < 0) {
                $rarest = $probability;
            }
        }

        \assert($rarest instanceof Fraction);
        // The fewest rolls n with n × p ≥ perFace, in integers: n = ⌈perFace × denominator / numerator⌉.
        $needed = $rarest->denominator->multiply(Natural::of($perFace));
        [$quotient, $remainder] = $needed->divide($rarest->numerator);
        $rolls = $quotient->toInt();

        if (null === $rolls || $rolls > Tally::MAX_ROLLS) {
            throw InvalidRolls::tooRare();
        }

        return $remainder->isZero() ? $rolls : $rolls + 1;
    }

    /**
     * @throws InvalidRolls
     */
    private static function check(int $faces): void
    {
        if ($faces < 2) {
            throw InvalidRolls::tooFewFaces();
        }

        if ($faces > self::MAX_FACES) {
            throw InvalidRolls::tooManyFaces(self::MAX_FACES);
        }
    }
}
