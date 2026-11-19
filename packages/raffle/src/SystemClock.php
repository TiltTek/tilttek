<?php

declare(strict_types=1);

namespace Tilttek\Raffle;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

/**
 * The clock of the system, for an application that gives none.
 *
 * @internal
 */
final readonly class SystemClock implements ClockInterface
{
    #[Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
