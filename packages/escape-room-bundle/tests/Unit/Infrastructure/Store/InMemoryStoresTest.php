<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Tests\Unit\Infrastructure\Store;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\EscapeRoom\Booking\BookingRefusal;
use Tilttek\EscapeRoom\Booking\SeatBooking;
use Tilttek\EscapeRoom\Contract\SeatLedger;
use Tilttek\EscapeRoom\RoomResult;
use Tilttek\EscapeRoomBundle\Application\Contract\GameStore;
use Tilttek\EscapeRoomBundle\Application\Contract\PartyBookings;
use Tilttek\EscapeRoomBundle\Application\Contract\ResultStore;
use Tilttek\EscapeRoomBundle\Application\Contract\ScreenStore;
use Tilttek\EscapeRoomBundle\Application\Screen;
use Tilttek\EscapeRoomBundle\Application\ScreenKind;
use Tilttek\EscapeRoomBundle\Infrastructure\Memory\InMemoryGameStore;
use Tilttek\EscapeRoomBundle\Infrastructure\Memory\InMemoryResultStore;
use Tilttek\EscapeRoomBundle\Infrastructure\Memory\InMemoryScreenStore;
use Tilttek\EscapeRoomBundle\Infrastructure\Memory\InMemorySeatLedger;

#[CoversClass(InMemoryGameStore::class)]
#[CoversClass(InMemoryResultStore::class)]
#[CoversClass(InMemoryScreenStore::class)]
#[CoversClass(InMemorySeatLedger::class)]
final class InMemoryStoresTest extends StoresTestCase
{
    public function testAResetForgetsEverything(): void
    {
        $games = new InMemoryGameStore();
        $results = new InMemoryResultStore();
        $screens = new InMemoryScreenStore();
        $ledger = new InMemorySeatLedger();
        $at = new DateTimeImmutable('2026-11-14 20:00 UTC');
        $games->save('g-1', ['room' => 'crypt', 'team' => 'team:a'], $at);
        $results->record('g-1', new RoomResult('crypt', ResourceReference::of('team', 'a'), null, 1, 1, 0, 0, 0, $at, true));
        $screens->show('g-1', new Screen(ScreenKind::Cleared, ResourceReference::of('member', '3'), $at));
        $ledger->hold(self::session('s-1'), new SeatBooking('a', 's-1', ResourceReference::of('member', 'ana'), 1, false), static fn (array $held): ?BookingRefusal => null);

        $games->reset();
        $results->reset();
        $screens->reset();
        $ledger->reset();

        self::assertNull($games->find('g-1'));
        self::assertSame([], $results->shown('crypt', 10));
        self::assertNull($screens->screen('g-1'));
        self::assertSame([], $ledger->bookings(self::session('s-1')));
    }

    #[Override]
    protected function games(): GameStore
    {
        return new InMemoryGameStore();
    }

    #[Override]
    protected function results(): ResultStore
    {
        return new InMemoryResultStore();
    }

    #[Override]
    protected function screens(): ScreenStore
    {
        return new InMemoryScreenStore();
    }

    #[Override]
    protected function ledger(): SeatLedger&PartyBookings
    {
        return new InMemorySeatLedger();
    }
}
