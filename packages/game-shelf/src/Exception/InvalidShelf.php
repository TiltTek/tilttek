<?php

declare(strict_types=1);

namespace Tilttek\GameShelf\Exception;

use InvalidArgumentException;

/**
 * A value of a shelf, a copy, a wish or a note is out of its shape or its bounds.
 */
final class InvalidShelf extends InvalidArgumentException implements ShelfException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
