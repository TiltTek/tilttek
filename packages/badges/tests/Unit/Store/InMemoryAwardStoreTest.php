<?php

declare(strict_types=1);

namespace Tilttek\Badges\Tests\Unit\Store;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionObject;
use Tilttek\Badges\Store\AwardStore;
use Tilttek\Badges\Store\InMemoryAwardStore;

#[CoversClass(InMemoryAwardStore::class)]
final class InMemoryAwardStoreTest extends AwardStoreTestCase
{
    #[Override]
    protected function store(): AwardStore
    {
        return new InMemoryAwardStore();
    }

    public function testResetLeavesTheStoreAsNew(): void
    {
        $store = new InMemoryAwardStore();

        foreach (new ReflectionObject($store)->getProperties() as $property) {
            $property->setValue($store, \is_int($property->getValue($store)) ? 7 : ['kept']);
        }

        $store->reset();

        self::assertEquals(new InMemoryAwardStore(), $store);
    }
}
