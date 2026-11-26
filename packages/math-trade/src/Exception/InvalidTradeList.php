<?php

declare(strict_types=1);

namespace Tilttek\MathTrade\Exception;

use InvalidArgumentException;

/**
 * A trade list the library refuses: an identifier out of bounds, two participants or two offers with one identifier,
 * a wish for an offer nobody makes or for one of the participant's own offers, an offer wished twice, a JSON document
 * that is not a trade list.
 */
final class InvalidTradeList extends InvalidArgumentException implements MathTradeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
