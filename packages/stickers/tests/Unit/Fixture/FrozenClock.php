<?php

declare(strict_types=1);

namespace Tilttek\Stickers\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

/**
 * A clock of tests: it gives the time it was set to, until it is moved.
 */
final class FrozenClock implements ClockInterface
{
    public function __construct(public DateTimeImmutable $now = new DateTimeImmutable('2026-11-13 09:30:00-05:00'))
    {
    }

    #[Override]
    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
