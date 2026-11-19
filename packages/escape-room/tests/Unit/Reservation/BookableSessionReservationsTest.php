<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Tests\Unit\Reservation;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\Reservation\Availability\WeeklyAvailability;
use Phil\Reservation\Bookable;
use Phil\Reservation\Rule\DurationLimits;
use Phil\Reservation\Rule\MaximumAdvance;
use Phil\Reservation\Rule\MinimumNotice;
use Phil\Reservation\Rule\SlotGrid;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\MockClock;
use Tilttek\EscapeRoom\Booking\BookingRefusal;
use Tilttek\EscapeRoom\Booking\SeatBooking;
use Tilttek\EscapeRoom\Booking\Session;
use Tilttek\EscapeRoom\Booking\SessionBooker;
use Tilttek\EscapeRoom\Contract\SessionReservations;
use Tilttek\EscapeRoom\Exception\BookingRefused;
use Tilttek\EscapeRoom\Exception\InvalidRoom;
use Tilttek\EscapeRoom\Reservation\BookableSessionReservations;
use Tilttek\EscapeRoom\Reservation\Contract\RoomBookables;
use Tilttek\EscapeRoom\Store\InMemorySeatLedger;
use Tilttek\EscapeRoom\Tests\Unit\Booking\SessionReservationsTestCase;
use Tilttek\EscapeRoom\Tests\Unit\Fixture\Crypt;

#[CoversClass(BookableSessionReservations::class)]
#[CoversClass(InMemorySeatLedger::class)]
final class BookableSessionReservationsTest extends SessionReservationsTestCase
{
    private const string NOW = '2026-11-13 20:00:00 UTC';

    public function testTheRulesOfTheBookableAreToldAsRefusals(): void
    {
        $ana = ResourceReference::of('member', 'ana');
        // Open from 18:00 to 23:00 on Saturdays, booked 2 hours ahead at least and 30 days at most, an hour long at most.
        $reservations = $this->ruled(new Bookable(
            capacity: 6,
            availability: new WeeklyAvailability([6 => [[18 * 60, 23 * 60]]], new DateTimeZone('UTC')),
            rules: [new MinimumNotice(120), new MaximumAdvance(30 * 24 * 60), new DurationLimits(maximum: 60)],
        ));

        self::assertSame(BookingRefusal::Closed, $reservations->reserve($this->at('2026-11-14 16:00', '2026-11-14 17:00'), new SeatBooking('a', 's', $ana, 1, false), 6));
        self::assertSame(BookingRefusal::TooSoon, $reservations->reserve($this->at('2026-11-13 21:00', '2026-11-13 21:30'), new SeatBooking('b', 's', $ana, 1, false), 6));
        self::assertSame(BookingRefusal::TooFarAhead, $reservations->reserve($this->at('2027-01-02 19:00', '2027-01-02 20:00'), new SeatBooking('c', 's', $ana, 1, false), 6));
        self::assertSame(BookingRefusal::OffSchedule, $reservations->reserve($this->at('2026-11-14 19:00', '2026-11-14 20:30'), new SeatBooking('d', 's', $ana, 1, false), 6));
        self::assertNull($reservations->reserve($this->at('2026-11-14 19:00', '2026-11-14 20:00'), new SeatBooking('e', 's', $ana, 1, false), 6));
    }

    public function testASessionTooShortOrOffTheGridIsOffSchedule(): void
    {
        $ana = ResourceReference::of('member', 'ana');
        $short = $this->ruled(new Bookable(capacity: 6, rules: [new DurationLimits(minimum: 90)]));
        $grid = $this->ruled(new Bookable(capacity: 6, rules: [new SlotGrid(30)]));

        self::assertSame(BookingRefusal::OffSchedule, $short->reserve($this->at('2026-11-14 19:00', '2026-11-14 20:00'), new SeatBooking('a', 's', $ana, 1, false), 6));
        self::assertNull($short->reserve($this->at('2026-11-14 19:00', '2026-11-14 20:30'), new SeatBooking('b', 's', $ana, 1, false), 6));
        self::assertSame(BookingRefusal::OffSchedule, $grid->reserve($this->at('2026-11-14 19:10', '2026-11-14 20:10'), new SeatBooking('c', 's', $ana, 1, false), 6));
        self::assertNull($grid->reserve($this->at('2026-11-14 19:30', '2026-11-14 20:30'), new SeatBooking('d', 's', $ana, 1, false), 6));
    }

    public function testTheSizeOfAPartyOutsideTheCapacityIsRefused(): void
    {
        $reservations = $this->ruled(null);
        $ana = ResourceReference::of('member', 'ana');

        self::assertSame(BookingRefusal::TooManyPlayers, $reservations->reserve(self::session('s'), new SeatBooking('a', 's', $ana, 7, false), 6));
        self::assertSame(BookingRefusal::TooFewPlayers, $reservations->reserve(self::session('s'), new SeatBooking('b', 's', $ana, 0, false), 6));
        self::assertSame([], $reservations->bookings(self::session('s')));
    }

    public function testThePartyOfAPrivateBookingTakesTheWholeCapacity(): void
    {
        $reservations = $this->ruled(null);
        $session = self::session('s');

        self::assertNull($reservations->reserve($session, new SeatBooking('a', 's', ResourceReference::of('team', 'owls'), 2, true), 6));
        // A private booking of 2 players holds the 6 seats: one more seat is taken.
        self::assertSame(BookingRefusal::Taken, $reservations->reserve($session, new SeatBooking('b', 's', ResourceReference::of('member', 'ana'), 1, false), 6));
    }

    public function testTheRoomBookablesAreAskedWithTheSessionAndTheCapacity(): void
    {
        $rooms = new class implements RoomBookables {
            /** @var list<array{string, int}> */
            public array $asked = [];

            #[Override]
            public function bookable(Session $session, int $capacity): Bookable
            {
                $this->asked[] = [$session->id, $capacity];

                return new Bookable($capacity);
            }
        };
        $reservations = new BookableSessionReservations(new InMemorySeatLedger(), $rooms, new MockClock(self::NOW));

        self::assertNull($reservations->reserve(self::session('s-7'), new SeatBooking('a', 's-7', ResourceReference::of('member', 'ana'), 1, false), 4));
        self::assertSame([['s-7', 4]], $rooms->asked);
    }

    public function testABookableThatDoesNotHoldTheCapacityOfTheRoomIsRefused(): void
    {
        $reservations = $this->ruled(new Bookable(capacity: 4));

        $this->expectException(InvalidRoom::class);
        $this->expectExceptionMessageIsOrContains('The bookable of the room "crypt" holds its 6 seats, not 4.');

        $reservations->reserve(self::session('s'), new SeatBooking('a', 's', ResourceReference::of('member', 'ana'), 1, false), 6);
    }

    public function testTheBookerTellsWhatTheBookableRefuses(): void
    {
        $clock = new MockClock(self::NOW);
        $reservations = new BookableSessionReservations(new InMemorySeatLedger(), clock: $clock);
        $booker = new SessionBooker($this->ruled(new Bookable(capacity: 6, rules: [new MinimumNotice(24 * 60)])), clock: $clock);

        try {
            $booker->book(Crypt::room(), self::session('s'), ResourceReference::of('member', 'ana'), 2, false, 'b-1');
            self::fail('The notice of the room is a day.');
        } catch (BookingRefused $bookingRefused) {
            self::assertSame(BookingRefusal::TooSoon, $bookingRefused->reason);
        }

        self::assertSame('b-2', new SessionBooker($reservations, clock: $clock)->book(Crypt::room(), self::session('s'), ResourceReference::of('member', 'ana'), 2, false, 'b-2')->id);
    }

    public function testAResetLedgerForgetsEveryBooking(): void
    {
        $ledger = new InMemorySeatLedger();
        $reservations = new BookableSessionReservations($ledger, clock: new MockClock(self::NOW));
        $reservations->reserve(self::session('s'), new SeatBooking('a', 's', ResourceReference::of('member', 'ana'), 1, false), 6);

        $ledger->reset();

        self::assertSame([], $reservations->bookings(self::session('s')));
    }

    #[Override]
    protected function reservations(): SessionReservations
    {
        return new BookableSessionReservations(new InMemorySeatLedger(), clock: new MockClock(self::NOW));
    }

    private function ruled(?Bookable $bookable): BookableSessionReservations
    {
        $rooms = $bookable instanceof Bookable ? new readonly class($bookable) implements RoomBookables {
            public function __construct(private Bookable $bookable)
            {
            }

            #[Override]
            public function bookable(Session $session, int $capacity): Bookable
            {
                return $this->bookable;
            }
        } : null;

        return new BookableSessionReservations(new InMemorySeatLedger(), $rooms, new MockClock(self::NOW));
    }

    private function at(string $start, string $end): Session
    {
        return new Session('s', 'crypt', new DateTimeImmutable($start.' UTC'), new DateTimeImmutable($end.' UTC'));
    }
}
