<?php

declare(strict_types=1);

namespace Tilttek\Quests\Tests\Unit\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionObject;
use Tilttek\Quests\Store\EnrolmentStore;
use Tilttek\Quests\Store\InMemoryEnrolmentStore;

#[CoversClass(InMemoryEnrolmentStore::class)]
final class InMemoryEnrolmentStoreTest extends EnrolmentStoreTestCase
{
    protected function store(): EnrolmentStore
    {
        return new InMemoryEnrolmentStore();
    }

    public function testResetLeavesTheStoreAsNew(): void
    {
        $store = new InMemoryEnrolmentStore();

        foreach (new ReflectionObject($store)->getProperties() as $property) {
            $property->setValue($store, \is_int($property->getValue($store)) ? 7 : ['kept']);
        }

        $store->reset();

        self::assertEquals(new InMemoryEnrolmentStore(), $store);
    }
}
