<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Event;

use Phil\Resource\ResourceReference;

/**
 * A session booked by a party, once the reservations hold it. It names identifiers only.
 */
final readonly class SeatsBooked
{
    public function __construct(
        public string $room,
        public string $session,
        public string $booking,
        public ResourceReference $party,
        public int $seats,
        public bool $private,
    ) {
    }
}
