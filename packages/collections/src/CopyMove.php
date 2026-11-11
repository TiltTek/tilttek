<?php

declare(strict_types=1);

namespace Tilttek\Collections;

use Phil\Resource\ResourceReference;

/**
 * A copy changing hands: from the member who gives it to the member who gets it.
 */
final readonly class CopyMove
{
    public function __construct(
        public string $copy,
        public ResourceReference $from,
        public ResourceReference $to,
    ) {
    }
}
