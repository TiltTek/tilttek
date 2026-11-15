<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens;

use Phil\Resource\ResourceReference;

/**
 * How many tokens of a kind a holder has.
 */
final readonly class Balance
{
    public function __construct(
        public string $kind,
        public ResourceReference $holder,
        public int $amount,
    ) {
    }
}
