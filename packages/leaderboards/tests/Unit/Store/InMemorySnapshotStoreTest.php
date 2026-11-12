<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Tests\Unit\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionObject;
use Tilttek\Leaderboards\Store\InMemorySnapshotStore;
use Tilttek\Leaderboards\Store\SnapshotStore;

#[CoversClass(InMemorySnapshotStore::class)]
final class InMemorySnapshotStoreTest extends SnapshotStoreTestCase
{
    protected function store(): SnapshotStore
    {
        return new InMemorySnapshotStore();
    }

    public function testResetLeavesTheStoreAsNew(): void
    {
        $store = new InMemorySnapshotStore();

        foreach (new ReflectionObject($store)->getProperties() as $property) {
            $property->setValue($store, \is_int($property->getValue($store)) ? 7 : ['kept']);
        }

        $store->reset();

        self::assertEquals(new InMemorySnapshotStore(), $store);
    }
}
