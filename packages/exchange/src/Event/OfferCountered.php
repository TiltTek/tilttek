<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Event;

use Tilttek\Exchange\Trade\Offer;

/**
 * The member an offer was made to answered with an offer of their own: the first one is closed, the counter-offer
 * open.
 */
final readonly class OfferCountered implements ExchangeEvent
{
    public function __construct(
        public Offer $offer,
        public Offer $counter,
    ) {
    }
}
