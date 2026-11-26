<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\Exception;

use DomainException;

/**
 * A gesture the state of the game refuses: a zone that is empty or unknown, a card the gesture cannot reach, a game
 * not started or already finished. The state is left as it was.
 */
final class IllegalGesture extends DomainException implements CardDeckException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
