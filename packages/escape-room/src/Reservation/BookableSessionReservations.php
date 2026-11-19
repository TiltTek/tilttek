<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Reservation;

use Override;
use Phil\Reservation\Bookable;
use Phil\Reservation\Booking;
use Phil\Reservation\BookingRequest;
use Phil\Reservation\Decision;
use Phil\Reservation\Interval;
use Phil\Reservation\ViolationCode;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Tilttek\EscapeRoom\Booking\BookingRefusal;
use Tilttek\EscapeRoom\Booking\SeatBooking;
use Tilttek\EscapeRoom\Booking\Session;
use Tilttek\EscapeRoom\Contract\SeatLedger;
use Tilttek\EscapeRoom\Contract\SessionReservations;
use Tilttek\EscapeRoom\Exception\InvalidRoom;
use Tilttek\EscapeRoom\Reservation\Contract\RoomBookables;

/**
 * The reservations of the sessions on `phil/reservation`: a room is a `Bookable` whose capacity is its largest team,
 * a session an interval of it. A private booking holds the whole capacity, a booking of seats as many units as its
 * seats. Each booking is checked by `Bookable::check()` against those the ledger holds, under the ledger's lock, and
 * what the `Bookable` refuses is told as a `BookingRefusal`:
 *
 * | `ViolationCode` | `BookingRefusal` |
 * |---|---|
 * | `Full` | `Taken` |
 * | `Quantity` | `TooFewPlayers` under one seat, `TooManyPlayers` over the capacity |
 * | `Unavailable` | `Closed` |
 * | `TooSoon` | `TooSoon` |
 * | `TooFarAhead` | `TooFarAhead` |
 * | `TooShort`, `TooLong`, `OffGrid` | `OffSchedule` |
 *
 * The service keeps nothing between two calls.
 */
final readonly class BookableSessionReservations implements SessionReservations
{
    /**
     * @param ?RoomBookables $rooms the `Bookable` of each room, with its rules; a `Bookable` with the capacity alone by default
     */
    public function __construct(
        private SeatLedger $ledger,
        private ?RoomBookables $rooms = null,
        private ClockInterface $clock = new NativeClock(),
    ) {
    }

    #[Override]
    public function bookings(Session $session): array
    {
        return $this->ledger->bookings($session);
    }

    /**
     * @throws InvalidRoom when the `Bookable` of the room does not hold its capacity
     */
    #[Override]
    public function reserve(Session $session, SeatBooking $booking, int $capacity): ?BookingRefusal
    {
        $bookable = $this->rooms?->bookable($session, $capacity) ?? new Bookable(capacity: $capacity);

        if ($bookable->capacity !== $capacity) {
            throw InvalidRoom::because(\sprintf('The bookable of the room "%s" holds its %d seats, not %d.', $session->room, $capacity, $bookable->capacity));
        }

        $interval = new Interval($session->startsAt, $session->endsAt);
        $request = new BookingRequest($interval, $this->clock->now(), $booking->private ? $capacity : $booking->seats);

        return $this->ledger->hold($session, $booking, static fn (array $held): ?BookingRefusal => self::refusal($request, $bookable->check(
            $request,
            array_map(static fn (SeatBooking $other): Booking => new Booking($other->id, $interval, $other->private ? $capacity : $other->seats), $held),
        )));
    }

    private static function refusal(BookingRequest $request, Decision $decision): ?BookingRefusal
    {
        if ($decision->allowed()) {
            return null;
        }

        return match ($decision->violations[0]->code) {
            ViolationCode::Full => BookingRefusal::Taken,
            ViolationCode::Quantity => $request->quantity <= 0 ? BookingRefusal::TooFewPlayers : BookingRefusal::TooManyPlayers,
            ViolationCode::Unavailable => BookingRefusal::Closed,
            ViolationCode::TooSoon => BookingRefusal::TooSoon,
            ViolationCode::TooFarAhead => BookingRefusal::TooFarAhead,
            ViolationCode::TooShort, ViolationCode::TooLong, ViolationCode::OffGrid => BookingRefusal::OffSchedule,
        };
    }
}
