<?php

declare(strict_types=1);

namespace Tilttek\MathTrade;

/**
 * One offer changing hands: the giver sends it, the receiver receives it.
 */
final readonly class Trade
{
    public function __construct(
        public string $offer,
        public string $giver,
        public string $receiver,
    ) {
    }
}
