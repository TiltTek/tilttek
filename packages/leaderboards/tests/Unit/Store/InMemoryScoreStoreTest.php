<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Tests\Unit\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionObject;
use Tilttek\Leaderboards\Store\InMemoryScoreStore;
use Tilttek\Leaderboards\Store\ScoreStore;

#[CoversClass(InMemoryScoreStore::class)]
final class InMemoryScoreStoreTest extends ScoreStoreTestCase
{
    protected function store(): ScoreStore
    {
        return new InMemoryScoreStore();
    }

    public function testResetLeavesTheStoreAsNew(): void
    {
        $store = new InMemoryScoreStore();

        foreach (new ReflectionObject($store)->getProperties() as $property) {
            $property->setValue($store, \is_int($property->getValue($store)) ? 7 : ['kept']);
        }

        $store->reset();

        self::assertEquals(new InMemoryScoreStore(), $store);
    }
}
