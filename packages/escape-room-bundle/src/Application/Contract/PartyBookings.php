<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Application\Contract;

use Phil\Resource\ResourceReference;
use Tilttek\EscapeRoom\Booking\SeatBooking;

/**
 * The bookings of a party, read and pseudonymised for an access or erasure request.
 */
interface PartyBookings
{
    /**
     * @return list<SeatBooking>
     */
    public function ofParty(ResourceReference $party): array;

    /**
     * Writes the pseudonym in place of the party in each of its bookings.
     *
     * @return int the bookings changed
     */
    public function eraseParty(ResourceReference $party, ResourceReference $pseudonym): int;
}
