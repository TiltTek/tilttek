<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Store;

use Tilttek\EscapeRoom\Booking\BookingRefusal;
use Tilttek\EscapeRoom\Booking\SeatBooking;
use Tilttek\EscapeRoom\Booking\Session;
use Tilttek\EscapeRoom\Contract\SessionReservations;

/**
 * The reservations in the memory of the process, for tests and examples. It checks again, as a reservation system
 * does, that a private booking is alone and that the seats held stay within the capacity.
 */
final class InMemorySessionReservations implements SessionReservations
{
    /** @var array<string, list<SeatBooking>> */
    private array $bookings = [];

    public function bookings(Session $session): array
    {
        return $this->bookings[$session->id] ?? [];
    }

    public function reserve(Session $session, SeatBooking $booking, int $capacity): ?BookingRefusal
    {
        $held = $this->bookings($session);
        $seats = $booking->private ? $capacity : $booking->seats;

        foreach ($held as $other) {
            if ($other->private || $booking->private) {
                return BookingRefusal::Taken;
            }

            $seats += $other->seats;
        }

        if ($seats > $capacity) {
            return BookingRefusal::Taken;
        }

        $this->bookings[$session->id] = [...$held, $booking];

        return null;
    }

    /**
     * Forgets every booking, between two tests or two requests of a worker.
     */
    public function reset(): void
    {
        $this->bookings = [];
    }
}
