<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\Exception;

use InvalidArgumentException;

/**
 * An identifier, a card, a deck, a gesture or a published game the library refuses: a name out of its pattern, a
 * deck out of its bounds, an array that does not decode.
 */
final class InvalidDefinition extends InvalidArgumentException implements CardDeckException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
