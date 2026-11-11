<?php

declare(strict_types=1);

namespace Tilttek\Progression\Exception;

use OutOfBoundsException;

/**
 * A streak asked for by a name none has.
 */
final class UnknownStreak extends OutOfBoundsException implements ProgressionException
{
    public static function named(string $name): self
    {
        return new self(\sprintf('No streak is named "%s".', $name));
    }
}
