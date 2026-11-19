<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Booking;

use Phil\Resource\ResourceReference;

/**
 * A booking of a session by a party: its seats, and whether it takes the whole room.
 */
final readonly class SeatBooking
{
    /**
     * @param string            $id      the booking's identifier, chosen by the application
     * @param ResourceReference $party   who booked: a member or a team, never an email address
     * @param int               $seats   the players of the party
     * @param bool              $private whether the party takes the whole room
     */
    public function __construct(
        public string $id,
        public string $session,
        public ResourceReference $party,
        public int $seats,
        public bool $private,
    ) {
    }
}
