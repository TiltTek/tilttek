<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Tests\Unit\Infrastructure\Store;

use PHPUnit\Framework\TestCase;
use Tilttek\Collections\Daily\DailyPick;
use Tilttek\Collections\Daily\DailyStore;
use Tilttek\Collections\Daily\PickOrigin;

/**
 * The contract every store of the calendar passes.
 */
abstract class DailyStoreTestCase extends TestCase
{
    abstract protected function store(): DailyStore;

    public function testAPickReplacesTheOneOfItsDay(): void
    {
        $store = $this->store();
        $store->save(new DailyPick('2026-11-09', 'pirate', PickOrigin::Drawn));
        $store->save(new DailyPick('2026-11-07', 'deckhand', PickOrigin::Rule));
        $store->save(new DailyPick('2026-11-07', 'captain', PickOrigin::Chosen));
        $store->save(new DailyPick('2026-11-08', 'medal', PickOrigin::Drawn));
        $store->save(new DailyPick('2026-11-10', 'medal', PickOrigin::Drawn));

        self::assertEquals(new DailyPick('2026-11-07', 'captain', PickOrigin::Chosen), $store->find('2026-11-07'));
        self::assertNull($store->find('2026-11-11'));
        self::assertSame(['2026-11-07', '2026-11-08', '2026-11-09'], array_map(static fn (DailyPick $pick): string => $pick->day, $store->between('2026-11-07', '2026-11-09')));
        self::assertSame(['2026-11-08'], array_map(static fn (DailyPick $pick): string => $pick->day, $store->between('2026-11-08', '2026-11-08')));

        self::assertTrue($store->remove('2026-11-08'));
        self::assertFalse($store->remove('2026-11-08'));
        self::assertNull($store->find('2026-11-08'));
        self::assertCount(3, $store->between('2026-11-01', '2026-11-30'));
    }
}
