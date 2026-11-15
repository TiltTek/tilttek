<?php

declare(strict_types=1);

namespace Tilttek\Bounties\Event;

use Phil\Resource\ResourceReference;

/**
 * Dispatched once for each winner of a bounty, after the points awarded to them are paid: the moment the application
 * celebrates.
 */
final readonly class BountyAwarded
{
    public function __construct(
        public string $bounty,
        public ResourceReference $winner,
        public int $amount,
        public string $unit,
    ) {
    }
}
