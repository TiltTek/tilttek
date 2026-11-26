<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\Exception;

use RuntimeException;

/**
 * A change made on a version of the game that is no longer the stored one: another change was recorded first. Read
 * the game again and decide anew.
 */
final class ConcurrentChange extends RuntimeException implements CardDeckException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
