<?php

declare(strict_types=1);

namespace Tilttek\Quests\Tests\Unit\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionObject;
use Tilttek\Quests\Store\DuelStore;
use Tilttek\Quests\Store\InMemoryDuelStore;

#[CoversClass(InMemoryDuelStore::class)]
final class InMemoryDuelStoreTest extends DuelStoreTestCase
{
    protected function store(): DuelStore
    {
        return new InMemoryDuelStore();
    }

    public function testResetLeavesTheStoreAsNew(): void
    {
        $store = new InMemoryDuelStore();

        foreach (new ReflectionObject($store)->getProperties() as $property) {
            $property->setValue($store, \is_int($property->getValue($store)) ? 7 : ['kept']);
        }

        $store->reset();

        self::assertEquals(new InMemoryDuelStore(), $store);
    }
}
