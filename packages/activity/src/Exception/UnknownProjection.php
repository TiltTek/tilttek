<?php

declare(strict_types=1);

namespace Tilttek\Activity\Exception;

use OutOfBoundsException;

/**
 * A projection asked for by a name no registered projection has.
 */
final class UnknownProjection extends OutOfBoundsException implements ActivityException
{
    public static function named(string $name): self
    {
        return new self(\sprintf('No projection is named "%s".', $name));
    }
}
