<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Store;

use Closure;
use Override;
use Tilttek\EscapeRoom\Booking\BookingRefusal;
use Tilttek\EscapeRoom\Booking\SeatBooking;
use Tilttek\EscapeRoom\Booking\Session;
use Tilttek\EscapeRoom\Contract\SeatLedger;

/**
 * The bookings in the memory of the process, for tests and examples: one process holds the only lock there is.
 */
final class InMemorySeatLedger implements SeatLedger
{
    /** @var array<string, list<SeatBooking>> */
    private array $bookings = [];

    #[Override]
    public function bookings(Session $session): array
    {
        return $this->bookings[$session->id] ?? [];
    }

    #[Override]
    public function hold(Session $session, SeatBooking $booking, Closure $decide): ?BookingRefusal
    {
        $held = $this->bookings($session);
        $refusal = $decide($held);

        if (!$refusal instanceof BookingRefusal) {
            $this->bookings[$session->id] = [...$held, $booking];
        }

        return $refusal;
    }

    /**
     * Forgets every booking, between two tests or two requests of a worker.
     */
    public function reset(): void
    {
        $this->bookings = [];
    }
}
