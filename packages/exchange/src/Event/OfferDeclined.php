<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Event;

use Tilttek\Exchange\Trade\Offer;

/**
 * An offer was declined by the member it was made to: its items are free again.
 */
final readonly class OfferDeclined implements ExchangeEvent
{
    public function __construct(
        public Offer $offer,
    ) {
    }
}
