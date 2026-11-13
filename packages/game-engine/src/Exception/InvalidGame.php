<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Exception;

use InvalidArgumentException;
use Throwable;

/**
 * A value the library refuses: a game's definition, its options, the seats of a table, a move's type or payload, or a
 * log that cannot be replayed.
 */
final class InvalidGame extends InvalidArgumentException implements GameEngineException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
