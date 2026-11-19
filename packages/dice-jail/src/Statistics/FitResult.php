<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Statistics;

/**
 * The goodness-of-fit test of one die: the rolls counted face by face, the chi-squared statistic, its degrees of
 * freedom, its p-value and the verdict, with the means that show a table how lucky the die has been.
 */
final readonly class FitResult
{
    /**
     * @param list<FaceCount> $faces        every face the die can show, in ascending order
     * @param ?float          $pValue       the probability that a fair die spreads its faces at least this unevenly;
     *                                      null when there are not enough rolls to trust it
     * @param int             $minimumRolls the fewest rolls for which the test is trusted
     */
    public function __construct(
        public array $faces,
        public int $rolls,
        public int $degreesOfFreedom,
        public float $statistic,
        public ?float $pValue,
        public int $minimumRolls,
        public Verdict $verdict,
        public float $observedMean,
        public float $expectedMean,
    ) {
    }

    /** Whether there were enough rolls for the verdict to rest on the p-value. */
    public function isConclusive(): bool
    {
        return Verdict::NotEnoughRolls !== $this->verdict;
    }

    /** How many more rolls the test needs before it is trusted; zero once it is. */
    public function rollsStillNeeded(): int
    {
        return max(0, $this->minimumRolls - $this->rolls);
    }
}
