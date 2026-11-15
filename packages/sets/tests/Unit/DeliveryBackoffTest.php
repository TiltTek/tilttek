<?php

declare(strict_types=1);

namespace Tilttek\Sets\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tilttek\Sets\DeliveryBackoff;
use Tilttek\Sets\Exception\InvalidSet;

#[CoversClass(DeliveryBackoff::class)]
final class DeliveryBackoffTest extends TestCase
{
    public function testTheDefaultsWaitAMinuteThenDoubleUpToADay(): void
    {
        $backoff = new DeliveryBackoff();

        self::assertSame(60, $backoff->firstDelay);
        self::assertSame(86_400, $backoff->longestDelay);
        self::assertSame(3, $backoff->reportAfter);
        self::assertSame(60, $backoff->delay(1));
        self::assertSame(120, $backoff->delay(2));
        self::assertSame(240, $backoff->delay(3));
        self::assertSame(61_440, $backoff->delay(11));
        self::assertSame(86_400, $backoff->delay(12));
        self::assertSame(86_400, $backoff->delay(\PHP_INT_MAX));
    }

    public function testTheDelayIsBoundedByTheLongest(): void
    {
        $backoff = new DeliveryBackoff(7, 7, 1);

        self::assertSame(7, $backoff->delay(0));
        self::assertSame(7, $backoff->delay(1));
        self::assertSame(7, $backoff->delay(5));
        self::assertSame(25, new DeliveryBackoff(10, 25)->delay(3));
    }

    public function testTheNextAttemptComesAfterTheDelay(): void
    {
        $now = new DateTimeImmutable('2026-11-15 10:00:00 UTC');

        self::assertEquals(new DateTimeImmutable('2026-11-15 10:02:00 UTC'), new DeliveryBackoff()->nextAttempt(2, $now));
    }

    public function testFailuresAreReportedFromTheirNumber(): void
    {
        $backoff = new DeliveryBackoff(reportAfter: 2);

        self::assertFalse($backoff->reports(1));
        self::assertTrue($backoff->reports(2));
        self::assertTrue($backoff->reports(3));
    }

    #[TestWith([0, 10])]
    #[TestWith([10, 9])]
    public function testADelayOutOfBoundsIsRefused(int $first, int $longest): void
    {
        $this->expectException(InvalidSet::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A delivery waits at least 1 second and the longest delay is not under the first; %d and %d are not.', $first, $longest));

        new DeliveryBackoff($first, $longest);
    }

    public function testFailuresAreReportedFromTheFirstAtTheEarliest(): void
    {
        self::assertSame(1, new DeliveryBackoff(reportAfter: 1)->reportAfter);

        $this->expectException(InvalidSet::class);
        $this->expectExceptionMessageIsOrContains('Failures are reported from the first one at the earliest; 0 is not.');

        new DeliveryBackoff(reportAfter: 0);
    }
}
