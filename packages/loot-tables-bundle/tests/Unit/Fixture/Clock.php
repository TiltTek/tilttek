<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Tests\Unit\Fixture;

use Symfony\Component\Clock\MockClock;

/**
 * The clock of the tests, at a fixed instant.
 */
final class Clock
{
    private function __construct()
    {
    }

    public static function at(string $instant = '2026-11-08 10:00:00'): MockClock
    {
        return new MockClock($instant);
    }
}
