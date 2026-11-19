<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Infrastructure\Memory;

use Closure;
use Override;
use Phil\Resource\ResourceReference;
use Symfony\Contracts\Service\ResetInterface;
use Tilttek\EscapeRoom\Booking\BookingRefusal;
use Tilttek\EscapeRoom\Booking\SeatBooking;
use Tilttek\EscapeRoom\Booking\Session;
use Tilttek\EscapeRoom\Contract\SeatLedger;
use Tilttek\EscapeRoomBundle\Application\Contract\PartyBookings;

/**
 * The bookings of the sessions in the memory of the process, for tests; `reset()`, tagged `kernel.reset`, forgets
 * them between two requests of a worker.
 */
final class InMemorySeatLedger implements SeatLedger, PartyBookings, ResetInterface
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

    #[Override]
    public function ofParty(ResourceReference $party): array
    {
        $found = [];

        foreach ($this->bookings as $bookings) {
            foreach ($bookings as $booking) {
                if ($booking->party->equals($party)) {
                    $found[] = $booking;
                }
            }
        }

        return $found;
    }

    #[Override]
    public function eraseParty(ResourceReference $party, ResourceReference $pseudonym): int
    {
        $erased = 0;

        foreach ($this->bookings as $session => $bookings) {
            $kept = [];

            foreach ($bookings as $booking) {
                if ($booking->party->equals($party)) {
                    $booking = new SeatBooking($booking->id, $booking->session, $pseudonym, $booking->seats, $booking->private);
                    ++$erased;
                }

                $kept[] = $booking;
            }

            $this->bookings[$session] = $kept;
        }

        return $erased;
    }

    #[Override]
    public function reset(): void
    {
        $this->bookings = [];
    }
}
