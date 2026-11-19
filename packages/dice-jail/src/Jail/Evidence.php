<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Jail;

use Tilttek\DiceJail\Statistics\FitResult;
use Tilttek\DiceJail\Statistics\Verdict;

/**
 * The statistics shown when a die went to jail, kept with the event: the verdict, the rolls it rested on, the p-value
 * and the means. The jail never needs them; they are what the table was told.
 */
final readonly class Evidence
{
    public function __construct(
        public Verdict $verdict,
        public int $rolls,
        public ?float $pValue,
        public float $observedMean,
        public float $expectedMean,
    ) {
    }

    public static function of(FitResult $result): self
    {
        return new self($result->verdict, $result->rolls, $result->pValue, $result->observedMean, $result->expectedMean);
    }
}
