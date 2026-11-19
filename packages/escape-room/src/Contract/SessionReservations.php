<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Contract;

use Tilttek\EscapeRoom\Booking\BookingRefusal;
use Tilttek\EscapeRoom\Booking\SeatBooking;
use Tilttek\EscapeRoom\Booking\Session;

/**
 * The reservations of the sessions, kept by the application: `Reservation\BookableSessionReservations` keeps them on
 * `phil/reservation`, where a room is a `Bookable` whose capacity is its largest team and a session an interval of it.
 *
 * The library checks the rules of the room — private or shared, the size of the party, the seats left — against
 * `bookings()`, then asks `reserve()`, which holds the seats in the application's own storage and checks again what
 * may have changed since: two parties booking the last seats at once do not both get them.
 */
interface SessionReservations
{
    /**
     * @return list<SeatBooking> the bookings of the session, in the order they were made
     */
    public function bookings(Session $session): array;

    /**
     * Holds the booking, or gives the reason the reservations refuse it: `Taken` when the seats were taken meanwhile,
     * or a rule of the reservations — the opening hours, the notice, the booking window, the lengths and starts.
     *
     * @param int $capacity the seats of the room: what a private booking holds
     *
     * @return ?BookingRefusal null once the booking is held
     */
    public function reserve(Session $session, SeatBooking $booking, int $capacity): ?BookingRefusal;
}
