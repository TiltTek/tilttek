<?php

declare(strict_types=1);

namespace Tilttek\MathTrade;

use Tilttek\MathTrade\Contract\Wish;
use Tilttek\MathTrade\Internal\Identifier;

/**
 * A wish for one offer: the offer this one would be traded for.
 */
final readonly class Want implements Wish
{
    /**
     * @param string $offer the identifier of the wished offer
     */
    public function __construct(public string $offer)
    {
        Identifier::check($offer, 'a wished offer');
    }

    public function offers(): array
    {
        return [$this->offer];
    }
}
