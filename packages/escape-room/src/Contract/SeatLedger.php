<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Contract;

use Closure;
use Tilttek\EscapeRoom\Booking\BookingRefusal;
use Tilttek\EscapeRoom\Booking\SeatBooking;
use Tilttek\EscapeRoom\Booking\Session;

/**
 * Where `Reservation\BookableSessionReservations` keeps the bookings of the sessions: the application's storage, which
 * decides and writes under one lock or transaction per session, so that two parties booking the last seats at once do
 * not both get them.
 */
interface SeatLedger
{
    /**
     * @return list<SeatBooking> the bookings of the session, in the order they were made
     */
    public function bookings(Session $session): array;

    /**
     * Reads the bookings of the session, asks `$decide` about the new one, and keeps it when `$decide` gives null —
     * all under the session's lock or inside one transaction.
     *
     * @param Closure(list<SeatBooking>): ?BookingRefusal $decide
     *
     * @return ?BookingRefusal what `$decide` gave: null once the booking is kept
     */
    public function hold(Session $session, SeatBooking $booking, Closure $decide): ?BookingRefusal;
}
