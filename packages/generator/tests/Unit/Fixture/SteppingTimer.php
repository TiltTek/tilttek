<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit\Fixture;

use Override;
use Tilttek\Generator\Timer;

/**
 * A clock that moves forward by a fixed number of milliseconds each time it is read, and counts its readings.
 */
final class SteppingTimer implements Timer
{
    public private(set) int $reads = 0;

    private float $now = 0.0;

    public function __construct(private readonly float $step)
    {
    }

    #[Override]
    public function now(): float
    {
        ++$this->reads;
        $now = $this->now;
        $this->now += $this->step;

        return $now;
    }
}
