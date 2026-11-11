<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Event;

use Tilttek\Exchange\Transfer\Transfer;

/**
 * A member gave points to another.
 */
final readonly class PointsTransferred implements ExchangeEvent
{
    public function __construct(
        public Transfer $transfer,
    ) {
    }
}
