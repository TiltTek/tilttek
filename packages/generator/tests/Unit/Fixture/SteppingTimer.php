<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit\Fixture;

use Override;
use Tilttek\Generator\Timer;

/**
 * A clock that moves forward by a fixed number of milliseconds each time it is read.
 */
final class SteppingTimer implements Timer
{
    private float $now = 0.0;

    public function __construct(private readonly float $step)
    {
    }

    #[Override]
    public function now(): float
    {
        $now = $this->now;
        $this->now += $this->step;

        return $now;
    }
}
