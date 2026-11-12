<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Tests\Unit\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionObject;
use Tilttek\Leaderboards\Store\AppearanceStore;
use Tilttek\Leaderboards\Store\InMemoryAppearanceStore;

#[CoversClass(InMemoryAppearanceStore::class)]
final class InMemoryAppearanceStoreTest extends AppearanceStoreTestCase
{
    protected function store(): AppearanceStore
    {
        return new InMemoryAppearanceStore();
    }

    public function testResetLeavesTheStoreAsNew(): void
    {
        $store = new InMemoryAppearanceStore();

        foreach (new ReflectionObject($store)->getProperties() as $property) {
            $property->setValue($store, \is_int($property->getValue($store)) ? 7 : ['kept']);
        }

        $store->reset();

        self::assertEquals(new InMemoryAppearanceStore(), $store);
    }
}
