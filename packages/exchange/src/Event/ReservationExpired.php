<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Event;

use Tilttek\Exchange\Reservation\Reservation;

/**
 * A reservation was not confirmed in time: the points came back to the member and the unit to the stock.
 */
final readonly class ReservationExpired implements ExchangeEvent
{
    public function __construct(
        public Reservation $reservation,
    ) {
    }
}
