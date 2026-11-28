<?php

declare(strict_types=1);

namespace Tilttek\RandomCharacter\Exception;

use RuntimeException;
use Throwable;

/**
 * A roll that could not give a ready-to-play character: a value outside the system's bounds, a generator that failed
 * or gave too few unique results, dice beyond their limits, or a field whose value cannot name the next generator.
 */
final class RollFailed extends RuntimeException implements RandomCharacterException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
