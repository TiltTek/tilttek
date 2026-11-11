<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Event;

use Tilttek\Exchange\Trade\Offer;

/**
 * An offer was accepted: both sides changed hands.
 */
final readonly class OfferAccepted implements ExchangeEvent
{
    public function __construct(
        public Offer $offer,
    ) {
    }
}
