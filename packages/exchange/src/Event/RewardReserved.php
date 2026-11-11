<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Event;

use Tilttek\Exchange\Reservation\Reservation;

/**
 * A member reserved a reward: the points are spent and the unit held, until the application confirms the delivery or cancels.
 */
final readonly class RewardReserved implements ExchangeEvent
{
    public function __construct(
        public Reservation $reservation,
    ) {
    }
}
