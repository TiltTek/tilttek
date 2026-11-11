<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Event;

use Tilttek\Exchange\Reservation\Reservation;

/**
 * The application delivered a reserved reward: the points stay spent.
 */
final readonly class ReservationConfirmed implements ExchangeEvent
{
    public function __construct(
        public Reservation $reservation,
    ) {
    }
}
