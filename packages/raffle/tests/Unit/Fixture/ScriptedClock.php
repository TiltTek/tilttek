<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

/**
 * A clock of tests that gives the times it was given, one per call, then the last one again.
 */
final class ScriptedClock implements ClockInterface
{
    /** @var list<DateTimeImmutable> */
    private array $times;

    public function __construct(string ...$times)
    {
        $this->times = array_values(array_map(static fn (string $time): DateTimeImmutable => new DateTimeImmutable($time), $times));
    }

    #[Override]
    public function now(): DateTimeImmutable
    {
        return 1 < \count($this->times) ? array_shift($this->times) : $this->times[0];
    }
}
