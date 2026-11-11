<?php

declare(strict_types=1);

namespace Tilttek\Collections\Tests\Unit\Store;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionObject;
use Tilttek\Collections\Store\CopyStore;
use Tilttek\Collections\Store\InMemoryCopyStore;

#[CoversClass(InMemoryCopyStore::class)]
final class InMemoryCopyStoreTest extends CopyStoreTestCase
{
    #[Override]
    protected function store(): CopyStore
    {
        return new InMemoryCopyStore();
    }

    public function testResetLeavesTheStoreAsNew(): void
    {
        $store = new InMemoryCopyStore();

        foreach (new ReflectionObject($store)->getProperties() as $property) {
            $property->setValue($store, \is_int($property->getValue($store)) ? 7 : ['kept']);
        }

        $store->reset();

        self::assertEquals(new InMemoryCopyStore(), $store);
    }
}
