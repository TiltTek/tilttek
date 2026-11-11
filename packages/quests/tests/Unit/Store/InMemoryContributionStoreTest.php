<?php

declare(strict_types=1);

namespace Tilttek\Quests\Tests\Unit\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionObject;
use Tilttek\Quests\Store\ContributionStore;
use Tilttek\Quests\Store\InMemoryContributionStore;

#[CoversClass(InMemoryContributionStore::class)]
final class InMemoryContributionStoreTest extends ContributionStoreTestCase
{
    protected function store(): ContributionStore
    {
        return new InMemoryContributionStore();
    }

    public function testResetLeavesTheStoreAsNew(): void
    {
        $store = new InMemoryContributionStore();

        foreach (new ReflectionObject($store)->getProperties() as $property) {
            $property->setValue($store, \is_int($property->getValue($store)) ? 7 : ['kept']);
        }

        $store->reset();

        self::assertEquals(new InMemoryContributionStore(), $store);
    }
}
