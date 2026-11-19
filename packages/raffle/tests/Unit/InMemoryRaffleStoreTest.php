<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Raffle\Exception\RaffleConflict;
use Tilttek\Raffle\Raffle;
use Tilttek\Raffle\SeedSource;
use Tilttek\Raffle\Stake;
use Tilttek\Raffle\Store\InMemoryRaffleStore;
use Tilttek\Raffle\Tests\Unit\Fixture\FrozenClock;

#[CoversClass(InMemoryRaffleStore::class)]
#[CoversClass(RaffleConflict::class)]
final class InMemoryRaffleStoreTest extends TestCase
{
    public function testItKeepsTheRafflesAndTheirEntrants(): void
    {
        $store = new InMemoryRaffleStore();
        $raffle = $this->raffle();

        self::assertNull($store->find('door-prize'));
        self::assertSame([], $store->entrants('door-prize'));

        $store->add($raffle);
        self::assertSame($raffle, $store->find('door-prize'));
        self::assertSame([], $store->entrants('door-prize'));

        $entered = $raffle->entered();
        self::assertTrue($store->enter($entered, '42'));
        self::assertSame($entered, $store->find('door-prize'));
        self::assertSame(['42'], $store->entrants('door-prize'));

        self::assertFalse($store->enter($entered->entered(), '42'));
        self::assertSame($entered, $store->find('door-prize'));

        $sealed = $entered->sealed(str_repeat('a', 64), new FrozenClock()->now);
        $store->save($sealed);
        self::assertSame($sealed, $store->find('door-prize'));
    }

    public function testAChangeFromAnotherVersionIsAConflict(): void
    {
        $store = new InMemoryRaffleStore();
        $raffle = $this->raffle();

        foreach ([static fn () => $store->save($raffle->entered()), static fn (): bool => $store->enter($raffle->entered(), 'm-1')] as $change) {
            try {
                $change();
                self::fail('A change of a raffle the store does not keep was taken.');
            } catch (RaffleConflict $conflict) {
                self::assertSame('The raffle door-prize was changed from version 1, but the store holds none.', $conflict->getMessage());
            }
        }

        $store->add($raffle);

        try {
            $store->add($raffle);
            self::fail('A raffle was added twice.');
        } catch (RaffleConflict $raffleConflict) {
            self::assertSame('The raffle door-prize was changed from version 0, but the store holds version 1.', $raffleConflict->getMessage());
        }

        try {
            $store->enter($raffle->entered()->entered(), 'm-1');
            self::fail('A change from a version the store does not hold was taken.');
        } catch (RaffleConflict $raffleConflict) {
            self::assertSame('The raffle door-prize was changed from version 2, but the store holds version 1.', $raffleConflict->getMessage());
            self::assertSame([], $store->entrants('door-prize'));
        }

        $this->expectException(RaffleConflict::class);
        $store->save($raffle);
    }

    private function raffle(): Raffle
    {
        return Raffle::open('door-prize', Stake::None, SeedSource::CommitReveal, 1, 0, new FrozenClock()->now, str_repeat('0', 64));
    }
}
