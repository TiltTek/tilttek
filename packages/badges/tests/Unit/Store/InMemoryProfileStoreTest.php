<?php

declare(strict_types=1);

namespace Tilttek\Badges\Tests\Unit\Store;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionObject;
use Tilttek\Badges\Store\InMemoryProfileStore;
use Tilttek\Badges\Store\ProfileStore;

#[CoversClass(InMemoryProfileStore::class)]
final class InMemoryProfileStoreTest extends ProfileStoreTestCase
{
    #[Override]
    protected function store(): ProfileStore
    {
        return new InMemoryProfileStore();
    }

    public function testResetLeavesTheStoreAsNew(): void
    {
        $store = new InMemoryProfileStore();

        foreach (new ReflectionObject($store)->getProperties() as $property) {
            $property->setValue($store, \is_int($property->getValue($store)) ? 7 : ['kept']);
        }

        $store->reset();

        self::assertEquals(new InMemoryProfileStore(), $store);
    }
}
