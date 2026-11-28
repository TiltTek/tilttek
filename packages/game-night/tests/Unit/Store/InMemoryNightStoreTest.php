<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Tests\Unit\Store;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\GameNight\Exception\NightConflict;
use Tilttek\GameNight\Exception\NightNotFound;
use Tilttek\GameNight\GameNight;
use Tilttek\GameNight\Store\InMemoryNightStore;

#[CoversClass(InMemoryNightStore::class)]
#[CoversClass(NightConflict::class)]
#[CoversClass(NightNotFound::class)]
final class InMemoryNightStoreTest extends TestCase
{
    private function night(): GameNight
    {
        return new GameNight('n-1', new DateTimeImmutable('2027-03-05 19:00'), new DateTimeImmutable('2027-03-05 23:00'), 'v', 'h');
    }

    public function testANightIsKeptAtTheNextVersion(): void
    {
        $store = new InMemoryNightStore();
        $kept = $store->save($this->night());

        self::assertSame(1, $kept->version);
        self::assertSame($kept, $store->night('n-1'));
        self::assertSame(2, $store->save($kept)->version);
    }

    public function testANewNightIsNotKeptTwice(): void
    {
        $store = new InMemoryNightStore();
        $store->save($this->night());

        $this->expectException(NightConflict::class);
        $this->expectExceptionMessageIs('The game night n-1 changed since it was read.');

        $store->save($this->night());
    }

    public function testAStaleNightIsNotKept(): void
    {
        $store = new InMemoryNightStore();
        $kept = $store->save($this->night());
        $store->save($kept);

        $this->expectException(NightConflict::class);

        $store->save($kept);
    }

    public function testANightNotKeptIsNotFound(): void
    {
        $this->expectException(NightNotFound::class);
        $this->expectExceptionMessageIs('No game night has the identifier n-1.');

        new InMemoryNightStore()->night('n-1');
    }
}
