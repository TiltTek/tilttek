<?php

declare(strict_types=1);

namespace Tilttek\Generator;

/**
 * A monotonic clock in milliseconds, for the time limits of a result and of a draw.
 */
interface Timer
{
    public function now(): float;
}
