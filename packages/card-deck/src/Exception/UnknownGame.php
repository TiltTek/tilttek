<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\Exception;

use RuntimeException;

/**
 * A game the store does not hold.
 */
final class UnknownGame extends RuntimeException implements CardDeckException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
