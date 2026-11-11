<?php

declare(strict_types=1);

namespace Tilttek\Badges\Tests\Unit\Store;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionObject;
use Tilttek\Badges\Store\GrantStore;
use Tilttek\Badges\Store\InMemoryGrantStore;

#[CoversClass(InMemoryGrantStore::class)]
final class InMemoryGrantStoreTest extends GrantStoreTestCase
{
    #[Override]
    protected function store(): GrantStore
    {
        return new InMemoryGrantStore();
    }

    public function testResetLeavesTheStoreAsNew(): void
    {
        $store = new InMemoryGrantStore();

        foreach (new ReflectionObject($store)->getProperties() as $property) {
            $property->setValue($store, \is_int($property->getValue($store)) ? 7 : ['kept']);
        }

        $store->reset();

        self::assertEquals(new InMemoryGrantStore(), $store);
    }
}
