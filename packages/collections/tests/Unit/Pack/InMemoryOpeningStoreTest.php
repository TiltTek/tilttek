<?php

declare(strict_types=1);

namespace Tilttek\Collections\Tests\Unit\Pack;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionObject;
use Tilttek\Collections\Pack\InMemoryOpeningStore;
use Tilttek\Collections\Pack\OpeningStore;

#[CoversClass(InMemoryOpeningStore::class)]
final class InMemoryOpeningStoreTest extends OpeningStoreTestCase
{
    #[Override]
    protected function store(): OpeningStore
    {
        return new InMemoryOpeningStore();
    }

    public function testResetLeavesTheStoreAsNew(): void
    {
        $store = new InMemoryOpeningStore();

        foreach (new ReflectionObject($store)->getProperties() as $property) {
            $property->setValue($store, \is_int($property->getValue($store)) ? 7 : ['kept']);
        }

        $store->reset();

        self::assertEquals(new InMemoryOpeningStore(), $store);
    }
}
