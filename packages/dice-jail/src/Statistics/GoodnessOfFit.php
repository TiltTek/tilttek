<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Statistics;

use Tilttek\DiceJail\Exception\InvalidRolls;

/**
 * Pearson's chi-squared test of goodness of fit: whether the rolls of a die are spread as a fair die spreads its
 * faces.
 *
 * The test is trusted only when every face is expected at least five times; below that the verdict is
 * `NotEnoughRolls` and no p-value is given. A low p-value says that a fair die rarely rolls like this, never that the
 * die is loaded: one die in a hundred tested crosses the 1 % line by chance alone.
 */
final readonly class GoodnessOfFit
{
    /** How many times each face is expected, at least, before the test is trusted. */
    public const int EXPECTED_PER_FACE = 5;

    /**
     * @throws InvalidRolls when the tally holds a face the die cannot show
     */
    public function test(Tally $tally, ExpectedFaces $expected): FitResult
    {
        foreach (array_keys($tally->counts) as $face) {
            if (!$expected->has($face)) {
                throw InvalidRolls::unexpectedFace($face);
            }
        }

        $rolls = $tally->total;
        $faces = [];
        $statistic = 0.0;
        $sum = 0;

        foreach ($expected->probabilities as $face => $probability) {
            $count = new FaceCount($face, $tally->count($face), $rolls * $probability->toFloat());
            $faces[] = $count;
            $sum += $face * $count->observed;

            if ($count->expected > 0.0) {
                $statistic += $count->difference() ** 2 / $count->expected;
            }
        }

        $minimumRolls = $expected->minimumRolls(self::EXPECTED_PER_FACE);
        $degrees = $expected->faces() - 1;
        $conclusive = $rolls >= $minimumRolls;
        $pValue = $conclusive ? ChiSquared::upperTail($statistic, $degrees) : null;

        return new FitResult(
            faces: $faces,
            rolls: $rolls,
            degreesOfFreedom: $degrees,
            statistic: $statistic,
            pValue: $pValue,
            minimumRolls: $minimumRolls,
            verdict: null === $pValue ? Verdict::NotEnoughRolls : Verdict::ofPValue($pValue),
            observedMean: 0 === $rolls ? 0.0 : $sum / $rolls,
            expectedMean: $expected->mean(),
        );
    }
}
