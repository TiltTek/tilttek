<?php

declare(strict_types=1);

namespace Tilttek\GameShelf\Exception;

use RuntimeException;

/**
 * The shelf does not allow the change: the copy, the wish or the note is not on it, the shelf is full, or the game is
 * hidden in the catalogue; nothing is kept.
 */
final class ShelfRefused extends RuntimeException implements ShelfException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
