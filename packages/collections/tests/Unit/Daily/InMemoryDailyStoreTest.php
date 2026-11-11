<?php

declare(strict_types=1);

namespace Tilttek\Collections\Tests\Unit\Daily;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionObject;
use Tilttek\Collections\Daily\DailyStore;
use Tilttek\Collections\Daily\InMemoryDailyStore;

#[CoversClass(InMemoryDailyStore::class)]
final class InMemoryDailyStoreTest extends DailyStoreTestCase
{
    #[Override]
    protected function store(): DailyStore
    {
        return new InMemoryDailyStore();
    }

    public function testResetLeavesTheStoreAsNew(): void
    {
        $store = new InMemoryDailyStore();

        foreach (new ReflectionObject($store)->getProperties() as $property) {
            $property->setValue($store, \is_int($property->getValue($store)) ? 7 : ['kept']);
        }

        $store->reset();

        self::assertEquals(new InMemoryDailyStore(), $store);
    }
}
