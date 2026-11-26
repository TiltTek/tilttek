<?php

declare(strict_types=1);

namespace Tilttek\MiniGames\Exception;

use InvalidArgumentException;
use Throwable;

/**
 * A value the library refuses: a day, a secret, a control, a grid, a game it does not know.
 */
final class InvalidMiniGame extends InvalidArgumentException implements MiniGameException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
