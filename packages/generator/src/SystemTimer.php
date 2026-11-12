<?php

declare(strict_types=1);

namespace Tilttek\Generator;

use Override;

/**
 * The system's monotonic clock.
 */
final readonly class SystemTimer implements Timer
{
    #[Override]
    public function now(): float
    {
        return hrtime(true) / 1_000_000;
    }
}
