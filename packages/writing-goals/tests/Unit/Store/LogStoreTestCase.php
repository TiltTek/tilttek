<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals\Tests\Unit\Store;

use PHPUnit\Framework\TestCase;
use Tilttek\WritingGoals\Contract\LogStore;
use Tilttek\WritingGoals\Day;

/**
 * The contract any log store passes: extend it with the store to test.
 */
abstract class LogStoreTestCase extends TestCase
{
    abstract protected function store(): LogStore;

    public function testCountsAreAddedPerDayAndNeverBelowZero(): void
    {
        $store = $this->store();
        $day = Day::fromString('2026-11-02');

        self::assertSame([], $store->progress('novel')->toArray());
        self::assertSame(100, $store->add('novel', $day, 100));
        self::assertSame(150, $store->add('novel', $day, 50));
        self::assertSame(30, $store->add('novel', $day->plus(1), 30));
        self::assertSame(0, $store->add('novel', $day->plus(1), -40));
        self::assertSame(7, $store->add('poems', $day, 7));

        self::assertSame(['2026-11-02' => 150, '2026-11-03' => 0], $store->progress('novel')->toArray());
    }

    public function testADayNeverCountsAboveTheMaximum(): void
    {
        $store = $this->store();
        $day = Day::fromString('2026-11-02');

        self::assertSame(1_000_000_000, $store->add('novel', $day, 1_000_000_000));
        self::assertSame(1_000_000_000, $store->add('novel', $day, 1_000_000_000));
        self::assertSame(999_999_999, $store->add('novel', $day, -1));
        self::assertSame(['2026-11-02' => 999_999_999], $store->progress('novel')->toArray());
    }

    public function testAGoalReachedIsDueUntilItIsDelivered(): void
    {
        $store = $this->store();
        $day = Day::fromString('2026-11-02');

        self::assertNull($store->undelivered('novel'));
        $store->markReached('novel', $day);
        $store->markReached('poems', $day->plus(1));
        self::assertSame('2026-11-02', $store->undelivered('novel')?->toString());

        // A notice of another day is not the one due: it is left due.
        $store->markDelivered('novel', $day->plus(1));
        self::assertSame('2026-11-02', $store->undelivered('novel')?->toString());
        $store->markDelivered('novel', $day);
        self::assertNull($store->undelivered('novel'));
        $store->markDelivered('novel', $day);
        self::assertSame('2026-11-03', $store->undelivered('poems')?->toString());

        // Reached again, the latest day is due; forgetting the goal forgets it.
        $store->markReached('poems', $day->plus(4));
        self::assertSame('2026-11-06', $store->undelivered('poems')?->toString());
        $store->forget('poems');
        self::assertNull($store->undelivered('poems'));
    }

    public function testACountIsReplacedAndAGoalForgotten(): void
    {
        $store = $this->store();
        $day = Day::fromString('2026-11-02');
        $store->add('novel', $day, 100);
        $store->set('novel', $day, 40);
        $store->set('novel', $day->plus(-1), 10);
        $store->add('poems', $day, 7);

        self::assertSame(['2026-11-01' => 10, '2026-11-02' => 40], $store->progress('novel')->toArray());

        $store->forget('novel');

        self::assertSame([], $store->progress('novel')->toArray());
        self::assertSame(['2026-11-02' => 7], $store->progress('poems')->toArray());
    }
}
