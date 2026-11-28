<?php

declare(strict_types=1);

namespace Tilttek\Celebrations\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: a key, an occurrence, a parameter, a moment, a page size or a limit out of bounds.
 */
final class InvalidCelebration extends InvalidArgumentException implements CelebrationException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
