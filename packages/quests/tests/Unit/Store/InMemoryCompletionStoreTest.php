<?php

declare(strict_types=1);

namespace Tilttek\Quests\Tests\Unit\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionObject;
use Tilttek\Quests\Store\CompletionStore;
use Tilttek\Quests\Store\InMemoryCompletionStore;

#[CoversClass(InMemoryCompletionStore::class)]
final class InMemoryCompletionStoreTest extends CompletionStoreTestCase
{
    protected function store(): CompletionStore
    {
        return new InMemoryCompletionStore();
    }

    public function testResetLeavesTheStoreAsNew(): void
    {
        $store = new InMemoryCompletionStore();

        foreach (new ReflectionObject($store)->getProperties() as $property) {
            $property->setValue($store, \is_int($property->getValue($store)) ? 7 : ['kept']);
        }

        $store->reset();

        self::assertEquals(new InMemoryCompletionStore(), $store);
    }
}
