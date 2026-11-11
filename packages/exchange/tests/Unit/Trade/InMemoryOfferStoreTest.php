<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Tests\Unit\Trade;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionObject;
use Tilttek\Exchange\Trade\InMemoryOfferStore;
use Tilttek\Exchange\Trade\OfferStore;

#[CoversClass(InMemoryOfferStore::class)]
final class InMemoryOfferStoreTest extends OfferStoreTestCase
{
    #[Override]
    protected function store(): OfferStore
    {
        return new InMemoryOfferStore();
    }

    public function testResetLeavesTheStoreAsNew(): void
    {
        $store = new InMemoryOfferStore();

        foreach (new ReflectionObject($store)->getProperties() as $property) {
            $property->setValue($store, \is_int($property->getValue($store)) ? 7 : ['kept']);
        }

        $store->reset();

        self::assertEquals(new InMemoryOfferStore(), $store);
    }
}
