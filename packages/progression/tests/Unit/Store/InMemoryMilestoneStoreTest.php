<?php

declare(strict_types=1);

namespace Tilttek\Progression\Tests\Unit\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionObject;
use Tilttek\Progression\ReachedMilestone;
use Tilttek\Progression\Store\InMemoryMilestoneStore;
use Tilttek\Progression\Store\MilestoneStore;

#[CoversClass(InMemoryMilestoneStore::class)]
#[CoversClass(ReachedMilestone::class)]
final class InMemoryMilestoneStoreTest extends MilestoneStoreTestCase
{
    protected function store(): MilestoneStore
    {
        return new InMemoryMilestoneStore();
    }

    public function testResetLeavesTheStoreAsNew(): void
    {
        $store = new InMemoryMilestoneStore();

        foreach (new ReflectionObject($store)->getProperties() as $property) {
            $property->setValue($store, \is_int($property->getValue($store)) ? 7 : ['kept']);
        }

        $store->reset();

        self::assertEquals(new InMemoryMilestoneStore(), $store);
    }
}
