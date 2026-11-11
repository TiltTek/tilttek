<?php

declare(strict_types=1);

namespace Tilttek\Badges\Tests\Unit\Store;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionObject;
use Tilttek\Badges\Store\InMemoryProgressStore;
use Tilttek\Badges\Store\ProgressStore;

#[CoversClass(InMemoryProgressStore::class)]
final class InMemoryProgressStoreTest extends ProgressStoreTestCase
{
    #[Override]
    protected function store(): ProgressStore
    {
        return new InMemoryProgressStore();
    }

    public function testResetLeavesTheStoreAsNew(): void
    {
        $store = new InMemoryProgressStore();

        foreach (new ReflectionObject($store)->getProperties() as $property) {
            $property->setValue($store, \is_int($property->getValue($store)) ? 7 : ['kept']);
        }

        $store->reset();

        self::assertEquals(new InMemoryProgressStore(), $store);
    }
}
