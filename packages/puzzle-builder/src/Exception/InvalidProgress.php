<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Exception;

use InvalidArgumentException;

/**
 * The stored state of a game that does not read back: a field missing or malformed, a puzzle the scenario does not
 * hold.
 */
final class InvalidProgress extends InvalidArgumentException implements PuzzleBuilderException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
