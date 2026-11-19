<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Reservation\Contract;

use Phil\Reservation\Bookable;
use Tilttek\EscapeRoom\Booking\Session;

/**
 * The `Bookable` of `phil/reservation` that stands for a room when one of its sessions is booked: its capacity, and the
 * rules the application gives it — opening hours, notice, booking window, lengths and starts of the sessions.
 */
interface RoomBookables
{
    /**
     * @param int $capacity the seats of the room, its largest team: the capacity the `Bookable` holds
     */
    public function bookable(Session $session, int $capacity): Bookable;
}
