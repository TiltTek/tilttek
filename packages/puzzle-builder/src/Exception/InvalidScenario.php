<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Exception;

use InvalidArgumentException;

/**
 * A scenario, a puzzle, an answer key or a hint the library does not take: a field missing or out of bounds, an
 * identifier used twice, a cycle in the unlocks, a puzzle no game can reach.
 */
final class InvalidScenario extends InvalidArgumentException implements PuzzleBuilderException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
