<?php

declare(strict_types=1);

namespace Tilttek\MathTrade\Exception;

use LengthException;

/**
 * A trade list larger than the limits allow: too many participants, offers or wishes, a group too large, a JSON
 * document too long. The solver's time grows with the size of the list, so it never reads a list out of bounds.
 */
final class LimitExceeded extends LengthException implements MathTradeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
