<?php

declare(strict_types=1);

namespace Tilttek\GameShelf\Tests\Unit\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\GameShelf\Copy;
use Tilttek\GameShelf\Exception\ShelfConflict;
use Tilttek\GameShelf\Shelf;
use Tilttek\GameShelf\Store\InMemoryShelfStore;

#[CoversClass(InMemoryShelfStore::class)]
#[CoversClass(ShelfConflict::class)]
final class InMemoryShelfStoreTest extends TestCase
{
    public function testAShelfIsKeptAtTheNextVersion(): void
    {
        $store = new InMemoryShelfStore();

        self::assertSame(0, $store->shelf('member')->version);
        $kept = $store->save(new Shelf('member', [new Copy('c1', 'game')]));
        self::assertSame(1, $kept->version);
        self::assertSame(1, $store->shelf('member')->version);
        self::assertSame(2, $store->save($kept->withCopy(new Copy('c2', 'game')))->version);
        self::assertCount(2, $store->shelf('member')->copies());
        self::assertSame(0, $store->shelf('other')->version);
    }

    public function testANewShelfIsRefusedWhenOneIsKept(): void
    {
        $store = new InMemoryShelfStore();
        $store->save(new Shelf('member'));

        $this->expectException(ShelfConflict::class);

        $store->save(new Shelf('member'));
    }

    public function testAShelfAtAnotherVersionIsRefused(): void
    {
        $store = new InMemoryShelfStore();
        $kept = $store->save(new Shelf('member'));
        $store->save($kept);

        $this->expectException(ShelfConflict::class);

        $store->save($kept);
    }

    public function testAShelfNeverKeptIsRefusedAtAVersion(): void
    {
        $this->expectException(ShelfConflict::class);

        new InMemoryShelfStore()->save(new Shelf('member', version: 1));
    }
}
