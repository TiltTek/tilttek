<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Trade;

use Phil\Resource\ResourceReference;

/**
 * An item changing hands: from the member who gives it to the member who gets it.
 */
final readonly class ItemMove
{
    public function __construct(
        public ResourceReference $item,
        public ResourceReference $from,
        public ResourceReference $to,
    ) {
    }
}
