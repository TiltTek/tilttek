<?php

declare(strict_types=1);

namespace Tilttek\Quests\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

/**
 * A clock that says what the test tells it.
 */
final class Clock implements ClockInterface
{
    private DateTimeImmutable $now;

    public function __construct(string $now = '2026-11-07 12:00:00 UTC')
    {
        $this->now = new DateTimeImmutable($now);
    }

    public function set(string $now): void
    {
        $this->now = new DateTimeImmutable($now);
    }

    #[Override]
    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
