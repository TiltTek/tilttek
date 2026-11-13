<?php

declare(strict_types=1);

namespace Tilttek\Bingo\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: an identifier, a square, a pool, a grid, a win pattern or a secret it cannot take.
 */
final class InvalidBingo extends InvalidArgumentException implements BingoException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
