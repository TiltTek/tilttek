<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Event;

use Tilttek\Exchange\Trade\Offer;

/**
 * A member offered a trade to another: the items offered are held until the offer closes.
 */
final readonly class OfferMade implements ExchangeEvent
{
    public function __construct(
        public Offer $offer,
    ) {
    }
}
