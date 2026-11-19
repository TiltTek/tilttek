<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Statistics;

use DateTimeImmutable;
use Tilttek\DiceJail\Contract\RollSource;
use Tilttek\DiceJail\Exception\InvalidRolls;

/**
 * The test of a die from the rolls the application recorded for it: read from the roll source, counted, and measured
 * against the faces of a fair die.
 */
final readonly class DieAssessor
{
    public function __construct(
        private RollSource $rolls,
        private GoodnessOfFit $test = new GoodnessOfFit(),
    ) {
    }

    /**
     * @param ?DateTimeImmutable $since the first moment counted, to judge only the rolls made after a release
     *
     * @throws InvalidRolls when the rolls hold a face the die cannot show, or are too many
     */
    public function assess(string $die, ExpectedFaces $expected, ?DateTimeImmutable $since = null): FitResult
    {
        return $this->test->test(Tally::of($this->rolls->faces($die, $since)), $expected);
    }
}
