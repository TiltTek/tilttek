<?php

declare(strict_types=1);

namespace Tilttek\Progression\Tests\Unit\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionObject;
use Tilttek\Progression\Store\InMemoryStreakStore;
use Tilttek\Progression\Store\StreakStore;
use Tilttek\Progression\Streak\StreakGesture;
use Tilttek\Progression\Streak\StreakState;

#[CoversClass(InMemoryStreakStore::class)]
#[CoversClass(StreakGesture::class)]
#[CoversClass(StreakState::class)]
final class InMemoryStreakStoreTest extends StreakStoreTestCase
{
    protected function store(): StreakStore
    {
        return new InMemoryStreakStore();
    }

    public function testResetLeavesTheStoreAsNew(): void
    {
        $store = new InMemoryStreakStore();

        foreach (new ReflectionObject($store)->getProperties() as $property) {
            $property->setValue($store, \is_int($property->getValue($store)) ? 7 : ['kept']);
        }

        $store->reset();

        self::assertEquals(new InMemoryStreakStore(), $store);
    }
}
