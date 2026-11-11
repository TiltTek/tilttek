<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Event;

use Tilttek\Exchange\Trade\Offer;

/**
 * An offer was withdrawn by the member who made it: its items are free again.
 */
final readonly class OfferWithdrawn implements ExchangeEvent
{
    public function __construct(
        public Offer $offer,
    ) {
    }
}
