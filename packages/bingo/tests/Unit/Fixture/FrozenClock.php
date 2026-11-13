<?php

declare(strict_types=1);

namespace Tilttek\Bingo\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

/**
 * A clock of tests: it gives the time it was set to, until it is moved.
 */
final class FrozenClock implements ClockInterface
{
    public function __construct(public DateTimeImmutable $now = new DateTimeImmutable('2026-11-12 20:00:00.000 UTC'))
    {
    }

    #[Override]
    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function move(string $modifier): void
    {
        $this->now = $this->now->modify($modifier);
    }
}
