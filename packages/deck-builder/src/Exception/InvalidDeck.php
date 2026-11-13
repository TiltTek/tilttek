<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: an identifier, a name, a quantity, a card, a format, a taboo list or a deck it cannot
 * take, or one that does not read from an array or JSON.
 */
final class InvalidDeck extends InvalidArgumentException implements DeckBuilderException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
