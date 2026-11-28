<?php

declare(strict_types=1);

namespace Tilttek\TavernGames\Exception;

use InvalidArgumentException;
use Throwable;

/**
 * A value the library refuses: a game it does not know, a number of dice or of samples out of its bounds.
 */
final class InvalidTavernGame extends InvalidArgumentException implements TavernGamesException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
