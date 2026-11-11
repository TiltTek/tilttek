<?php

declare(strict_types=1);

namespace Tilttek\Progression\Exception;

use OutOfBoundsException;

/**
 * A track asked for by a name none has.
 */
final class UnknownTrack extends OutOfBoundsException implements ProgressionException
{
    public static function named(string $name): self
    {
        return new self(\sprintf('No track is named "%s".', $name));
    }
}
