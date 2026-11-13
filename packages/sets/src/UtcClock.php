<?php

declare(strict_types=1);

namespace Tilttek\Sets;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Psr\Clock\ClockInterface;

/**
 * The clock of the system in UTC, for an application that gives none.
 */
final readonly class UtcClock implements ClockInterface
{
    #[Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
