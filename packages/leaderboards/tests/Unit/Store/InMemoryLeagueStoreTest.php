<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Tests\Unit\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionObject;
use Tilttek\Leaderboards\Store\InMemoryLeagueStore;
use Tilttek\Leaderboards\Store\LeagueStore;

#[CoversClass(InMemoryLeagueStore::class)]
final class InMemoryLeagueStoreTest extends LeagueStoreTestCase
{
    protected function store(): LeagueStore
    {
        return new InMemoryLeagueStore();
    }

    public function testResetLeavesTheStoreAsNew(): void
    {
        $store = new InMemoryLeagueStore();

        foreach (new ReflectionObject($store)->getProperties() as $property) {
            $property->setValue($store, \is_int($property->getValue($store)) ? 7 : ['kept']);
        }

        $store->reset();

        self::assertEquals(new InMemoryLeagueStore(), $store);
    }
}
