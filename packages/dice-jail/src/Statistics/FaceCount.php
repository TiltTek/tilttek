<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Statistics;

/**
 * One face of the die: how many times it came up, and how many times a fair die would show it over as many rolls.
 */
final readonly class FaceCount
{
    public function __construct(
        public int $face,
        public int $observed,
        public float $expected,
    ) {
    }

    /** The observed count less the expected one: below zero when the face came up less than its share. */
    public function difference(): float
    {
        return $this->observed - $this->expected;
    }
}
