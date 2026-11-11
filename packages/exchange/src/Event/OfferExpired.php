<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Event;

use Tilttek\Exchange\Trade\Offer;

/**
 * An offer went unanswered past its lifetime: its items are free again.
 */
final readonly class OfferExpired implements ExchangeEvent
{
    public function __construct(
        public Offer $offer,
    ) {
    }
}
