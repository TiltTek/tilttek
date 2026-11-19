<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Tests\Unit\Booking;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\EscapeRoom\Booking\BookingRefusal;
use Tilttek\EscapeRoom\Booking\SeatBooking;
use Tilttek\EscapeRoom\Booking\Session;
use Tilttek\EscapeRoom\Contract\SessionReservations;

/**
 * The scenarios every implementation of `SessionReservations` passes: the seats held within the capacity, a private
 * booking alone in its session, the sessions apart from each other, the bookings in the order they were made.
 */
abstract class SessionReservationsTestCase extends TestCase
{
    abstract protected function reservations(): SessionReservations;

    public function testTheSeatsHeldStayWithinTheCapacity(): void
    {
        $reservations = $this->reservations();
        $session = self::session('s-1');
        $ana = ResourceReference::of('member', 'ana');

        self::assertNull($reservations->reserve($session, new SeatBooking('a', 's-1', $ana, 4, false), 6));
        self::assertSame(BookingRefusal::Taken, $reservations->reserve($session, new SeatBooking('b', 's-1', $ana, 3, false), 6));
        self::assertNull($reservations->reserve($session, new SeatBooking('c', 's-1', $ana, 2, false), 6));
        self::assertSame(BookingRefusal::Taken, $reservations->reserve($session, new SeatBooking('d', 's-1', $ana, 1, false), 6));
        self::assertSame(['a', 'c'], $this->ids($reservations->bookings($session)));
    }

    public function testAPrivateBookingIsAloneInItsSession(): void
    {
        $reservations = $this->reservations();
        $shared = self::session('s-1');
        $private = self::session('s-2');
        $ana = ResourceReference::of('member', 'ana');

        self::assertNull($reservations->reserve($shared, new SeatBooking('a', 's-1', $ana, 1, false), 6));
        self::assertSame(BookingRefusal::Taken, $reservations->reserve($shared, new SeatBooking('b', 's-1', $ana, 2, true), 6));
        self::assertNull($reservations->reserve($private, new SeatBooking('c', 's-2', $ana, 2, true), 6));
        self::assertSame(BookingRefusal::Taken, $reservations->reserve($private, new SeatBooking('d', 's-2', $ana, 1, false), 6));
        self::assertSame(['a'], $this->ids($reservations->bookings($shared)));
        self::assertSame(['c'], $this->ids($reservations->bookings($private)));
    }

    public function testTheWholeCapacityIsBookedBySeats(): void
    {
        $reservations = $this->reservations();
        $session = self::session('s-3');

        self::assertNull($reservations->reserve($session, new SeatBooking('g', 's-3', ResourceReference::of('team', 'owls'), 6, false), 6));
        self::assertSame(BookingRefusal::Taken, $reservations->reserve($session, new SeatBooking('h', 's-3', ResourceReference::of('team', 'cats'), 1, false), 6));
    }

    public function testABookingIsReadBackWholeAndAnEmptySessionHasNone(): void
    {
        $reservations = $this->reservations();
        $session = self::session('s-4');
        $owls = ResourceReference::of('team', 'owls');

        self::assertSame([], $reservations->bookings($session));
        self::assertNull($reservations->reserve($session, new SeatBooking('k', 's-4', $owls, 3, true), 6));
        self::assertEquals([new SeatBooking('k', 's-4', $owls, 3, true)], $reservations->bookings($session));
        self::assertSame([], $reservations->bookings(self::session('s-5')));
    }

    protected static function session(string $id): Session
    {
        return new Session($id, 'crypt', new DateTimeImmutable('2026-11-14 19:00 UTC'), new DateTimeImmutable('2026-11-14 20:00 UTC'));
    }

    /**
     * @param list<SeatBooking> $bookings
     *
     * @return list<string>
     */
    private function ids(array $bookings): array
    {
        return array_map(static fn (SeatBooking $booking): string => $booking->id, $bookings);
    }
}
